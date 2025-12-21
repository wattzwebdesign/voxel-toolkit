<?php
/**
 * Saved Search Model Class
 *
 * Data model for saved searches stored in vt_saved_searches table.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search_Model {

    private $id;
    private $user_id;
    private $title;
    private $details;
    private $created_at;
    private $published_as;
    private $notification;

    private static $instances = [];

    /**
     * Constructor
     */
    private function __construct($data) {
        $this->id = absint($data['id']);
        $this->title = $data['title'];
        $this->user_id = absint($data['user_id']);
        $this->details = $data['details'];
        $this->created_at = $data['created_at'];
        $this->published_as = $data['published_as'];
        $this->notification = $data['notification'];
    }

    /**
     * Get filters to ignore when displaying saved search
     */
    public function get_ignore_filters() {
        return ['open-now', 'order-by', 'ui-heading'];
    }

    /**
     * Get search details as array
     */
    public function get_details() {
        return json_decode($this->details, true);
    }

    /**
     * Get search title
     */
    public function get_title() {
        return $this->title;
    }

    /**
     * Get notification status
     */
    public function get_notification() {
        return $this->notification;
    }

    /**
     * Get saved search data for display
     */
    public function get_saved_search_to_display() {
        $details = json_decode($this->details, true);
        $post_type = isset($details['post_type']) && $details['post_type'] ? \Voxel\Post_Type::get($details['post_type']) : null;
        $filters_to_display = [];

        foreach ($details as $key => $value) {
            if ($key === 'post_type') {
                continue;
            }

            $filter = $post_type && is_object($post_type->get_filter($key)) ? clone $post_type->get_filter($key) : null;

            if ($filter && !in_array($filter->get_type(), $this->get_ignore_filters())) {
                $filter->set_value($value);

                $display_value = $filter->get_value();

                // Convert term slugs to names for terms filter
                if ($filter->get_type() === 'terms' && !empty($value)) {
                    $display_value = $this->get_term_names_from_slugs($filter, $value);
                }

                $props = [
                    'id' => $filter->get_key(),
                    'key' => $filter->get_key(),
                    'label' => $filter->get_label(),
                    'icon' => \Voxel\get_icon_markup($filter->get_icon()),
                    'type' => $filter->get_type(),
                    'value' => $display_value,
                    'props' => $filter->frontend_props(),
                    'isDeleting' => false,
                    'isTogglingNotification' => false,
                    'isEditingTitle' => false,
                ];

                if ($key === 'recurring-date') {
                    $presets = \Voxel\get_range_presets();
                    if (array_key_exists($value, $presets)) {
                        $props['preset'] = $presets[$value];
                    }
                }

                $filters_to_display[] = $props;
            }
        }

        $data = [
            'id' => $this->get_id(),
            'time' => $this->get_created_at(),
            'filters' => $filters_to_display,
            'title' => $this->get_title(),
            'post_type' => [
                'id' => $post_type ? $post_type->get_key() : null,
                'label' => $post_type ? $post_type->get_label() : null,
                'icon' => $post_type ? \Voxel\get_icon_markup($post_type->get_icon()) : null,
                'value' => $post_type ? $value : null,
                'archive_link' => $post_type ? $post_type->get_archive_link() : null,
            ],
            'notification' => $this->get_notification() ? true : false,
            'params' => $this->get_details(),
        ];

        return $data;
    }

    /**
     * Convert term slugs to term names for display
     */
    private function get_term_names_from_slugs($filter, $value) {
        $taxonomy_key = null;

        if (method_exists($filter, 'get_taxonomy')) {
            $taxonomy = $filter->get_taxonomy();
            if ($taxonomy) {
                $taxonomy_key = $taxonomy->get_key();
            }
        }

        if (!$taxonomy_key) {
            $props = $filter->frontend_props();
            if (isset($props['taxonomy']['key'])) {
                $taxonomy_key = $props['taxonomy']['key'];
            }
        }

        if (!$taxonomy_key) {
            return $value;
        }

        $slugs = array_map('trim', explode(',', $value));
        $names = [];

        foreach ($slugs as $slug) {
            $term = get_term_by('slug', $slug, $taxonomy_key);
            if ($term && !is_wp_error($term)) {
                $names[] = $term->name;
            } else {
                $names[] = ucfirst(str_replace(['-', '_'], ' ', $slug));
            }
        }

        return implode(', ', $names);
    }

    /**
     * Create a new saved search
     */
    public static function create(array $data): self {
        global $wpdb;

        $data = array_merge([
            'id' => null,
            'title' => null,
            'user_id' => null,
            'published_as' => null,
            'details' => null,
            'notification' => null,
            'created_at' => current_time('mysql', true),
        ], $data);

        $sql = static::_generate_insert_query($data);
        $wpdb->query($sql);
        $data['id'] = $wpdb->insert_id;

        return new self($data);
    }

    /**
     * Get user ID
     */
    public function get_user_id() {
        return $this->user_id;
    }

    /**
     * Get ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get created at timestamp
     */
    public function get_created_at() {
        return $this->created_at;
    }

    /**
     * Get published as post ID
     */
    public function get_published_as() {
        return $this->published_as;
    }

    /**
     * Get the user who created this search
     */
    public function get_user() {
        return \Voxel\User::get($this->get_user_id());
    }

    /**
     * Check if current user can edit this search
     */
    public function is_editable_by_current_user(): bool {
        return absint($this->get_user_id()) === absint(get_current_user_id());
    }

    /**
     * Get a saved search by ID or data array
     */
    public static function get($id) {
        if (is_array($id)) {
            $data = $id;
            $id = $data['id'];
            if (!array_key_exists($id, static::$instances)) {
                static::$instances[$id] = new static($data);
            }
        } elseif (is_numeric($id)) {
            if (!array_key_exists($id, static::$instances)) {
                $results = static::query([
                    'id' => $id,
                    'limit' => 1,
                ]);
                static::$instances[$id] = isset($results[0]) ? $results[0] : null;
            }
        } elseif ($id === null) {
            return null;
        }

        return static::$instances[$id] ?? null;
    }

    /**
     * Force get a saved search (bypass cache)
     */
    public static function force_get($id) {
        unset(static::$instances[$id]);
        return static::get($id);
    }

    /**
     * Delete this saved search
     */
    public function delete() {
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}vt_saved_searches WHERE id = %d",
            $this->get_id()
        ));
        unset(static::$instances[$this->get_id()]);
    }

    /**
     * Create a dummy saved search for testing
     */
    public static function dummy($array = []) {
        $array = array_merge([
            'id' => 123,
            'title' => 'Saved search title',
            'user_id' => 0,
            'details' => '{}',
            'created_at' => '2021-09-01 12:00:00',
            'published_as' => 1,
            'notification' => 1,
        ], $array);

        return new self($array);
    }

    /**
     * Generate search query SQL
     */
    public static function _generate_search_query(array $args) {
        global $wpdb;

        $args = array_merge([
            'id' => null,
            'title' => null,
            'user_id' => null,
            'published_as' => null,
            'order_by' => 'created_at',
            'order' => 'desc',
            'offset' => null,
            'limit' => 10,
            'created_at' => null,
            'post_type' => null,
            'notification' => null,
        ], $args);

        $where_clauses = [];
        $orderby_clauses = [];
        $select_clauses = ['searches.*'];

        if (!is_null($args['id'])) {
            $where_clauses[] = sprintf('searches.id = %d', absint($args['id']));
        }

        if (!is_null($args['title'])) {
            $where_clauses[] = sprintf("searches.title = '%s'", esc_sql($args['title']));
        }

        if (!is_null($args['user_id'])) {
            if ($args['user_id'] < 0) {
                $where_clauses[] = sprintf('NOT(searches.user_id <=> %d)', absint($args['user_id']));
            } else {
                $where_clauses[] = sprintf('searches.user_id = %d', absint($args['user_id']));
            }
        }

        if (!is_null($args['notification'])) {
            $where_clauses[] = sprintf('searches.notification = %d', absint($args['notification']));
        }

        if (!is_null($args['published_as'])) {
            if ($args['published_as'] < 0) {
                $where_clauses[] = sprintf('NOT(searches.published_as <=> %d)', absint($args['published_as']));
            } else {
                $where_clauses[] = sprintf('searches.published_as = %d', absint($args['published_as']));
            }
        }

        if (!is_null($args['post_type'])) {
            $where_clauses[] = sprintf(
                "JSON_UNQUOTE(JSON_EXTRACT(searches.details, '$.post_type')) = '%s'",
                esc_sql($args['post_type'])
            );
        }

        if (!is_null($args['order_by'])) {
            $order = $args['order'] === 'asc' ? 'ASC' : 'DESC';
            if ($args['order_by'] === 'created_at') {
                $orderby_clauses[] = "searches.created_at {$order}";
            }
        }

        if (!is_null($args['created_at'])) {
            $timestamp = strtotime($args['created_at']);
            if ($timestamp) {
                $where_clauses[] = $wpdb->prepare("searches.created_at >= %s", date('Y-m-d H:i:s', $timestamp));
            }
        }

        $wheres = '';
        if (!empty($where_clauses)) {
            $wheres = sprintf('WHERE %s', join(' AND ', $where_clauses));
        }

        $orderbys = '';
        if (!empty($orderby_clauses)) {
            $orderbys = sprintf('ORDER BY %s', join(", ", $orderby_clauses));
        }

        $limit = '';
        if (!is_null($args['limit'])) {
            $limit = sprintf('LIMIT %d', absint($args['limit']));
        }

        $offset = '';
        if (!is_null($args['offset'])) {
            $offset = sprintf('OFFSET %d', absint($args['offset']));
        }

        $selects = join(', ', $select_clauses);

        return "SELECT {$selects} FROM {$wpdb->prefix}vt_saved_searches AS searches
                {$wheres}
                GROUP BY searches.id
                {$orderbys}
                {$limit} {$offset}";
    }

    /**
     * Generate insert query SQL
     */
    public static function _generate_insert_query(array $data) {
        global $wpdb;

        $escaped_data = [];

        foreach (['id', 'user_id', 'published_as'] as $column_name) {
            if (isset($data[$column_name])) {
                $escaped_data[$column_name] = absint($data[$column_name]);
            }
        }

        if (isset($data['notification'])) {
            $escaped_data['notification'] = absint($data['notification']);
        }

        if (isset($data['title'])) {
            $escaped_data['title'] = sprintf("'%s'", esc_sql($data['title']));
        }

        if (isset($data['details']) && is_array($data['details'])) {
            $data['details'] = wp_json_encode($data['details']);
        }

        foreach (['details', 'created_at'] as $column_name) {
            if (isset($data[$column_name])) {
                $escaped_data[$column_name] = sprintf("'%s'", esc_sql($data[$column_name]));
            }
        }

        $columns = join(', ', array_map(function($column_name) {
            return sprintf('`%s`', esc_sql($column_name));
        }, array_keys($escaped_data)));

        $values = join(', ', $escaped_data);

        $on_duplicate = join(', ', array_map(function($column_name) {
            return sprintf('`%s`=VALUES(`%s`)', $column_name, $column_name);
        }, array_keys($escaped_data)));

        return "INSERT INTO {$wpdb->prefix}vt_saved_searches ($columns) VALUES ($values)
                ON DUPLICATE KEY UPDATE $on_duplicate";
    }

    /**
     * Query saved searches
     */
    public static function query(array $args): array {
        global $wpdb;

        $sql = static::_generate_search_query($args);
        $results = $wpdb->get_results($sql, ARRAY_A);

        if (!is_array($results)) {
            return [];
        }

        return array_map([__CLASS__, 'get'], $results);
    }

    /**
     * Get all saved searches
     */
    public static function get_all() {
        return static::query([]);
    }

    /**
     * Update saved search
     */
    public function update($data_or_key, $value = null) {
        global $wpdb;

        if (is_array($data_or_key)) {
            $data = $data_or_key;
        } else {
            $data = [];
            $data[$data_or_key] = $value;
        }

        $data['id'] = $this->get_id();

        $insert_query = static::_generate_insert_query($data);
        return $wpdb->query($insert_query);
    }
}
