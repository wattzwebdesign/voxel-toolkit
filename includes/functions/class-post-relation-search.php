<?php
/**
 * Post Relation Search
 *
 * Adds searchable dropdown to post relations filter in search forms.
 * Enables AJAX-powered search instead of scroll-only behavior.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Post_Relation_Search {

    /**
     * Constructor
     */
    public function __construct() {
        // Register scripts
        add_action('wp_enqueue_scripts', array($this, 'register_assets'));
        add_action('admin_enqueue_scripts', array($this, 'register_assets'));

        // Render enhanced template on search form widget
        add_filter('elementor/widget/render_content', array($this, 'render_widget'), 10, 2);

        // AJAX handlers for getting posts
        add_action('voxel_ajax_vt_relations_get_posts', array($this, 'get_posts_for_relations_filter'));
        add_action('voxel_ajax_nopriv_vt_relations_get_posts', array($this, 'get_posts_for_relations_filter'));

        // Enqueue assets in Elementor preview
        add_action('elementor/frontend/widget/before_render', array($this, 'enqueue_assets_elementor_preview'), 999);
    }

    /**
     * Register assets
     */
    public function register_assets() {
        wp_register_script(
            'vt-relations-filter',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/relations-filter.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );
    }

    /**
     * Enqueue assets in Elementor preview
     */
    public function enqueue_assets_elementor_preview($widget) {
        if (function_exists('\Voxel\is_elementor_preview') && \Voxel\is_elementor_preview()) {
            wp_enqueue_script('vt-relations-filter');
        }
    }

    /**
     * Render enhanced relations filter template
     */
    public function render_widget($widget_content, $widget) {
        if ($widget->get_name() !== 'ts-search-form') {
            return $widget_content;
        }

        $settings = $widget->get_settings_for_display();
        $post_types = $settings['ts_choose_post_types'] ?? [];

        foreach ($post_types as $post_type_key) {
            $post_type = \Voxel\Post_Type::get($post_type_key);
            if ($post_type && $post_type->is_managed_by_voxel()) {
                $filters = $post_type->get_filters();
                foreach ($filters as $filter) {
                    if ($filter->get_type() === 'relations') {
                        wp_enqueue_script('vt-relations-filter');
                        require VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/widgets/search-form/relations-filter.php';
                        break;
                    }
                }
            }
        }

        return $widget_content;
    }

    /**
     * AJAX handler for getting posts for relations filter
     */
    public function get_posts_for_relations_filter() {
        try {
            $post_type_key = $_GET['post_type'] ?? '';
            $post_type = \Voxel\Post_Type::get($post_type_key);

            if (!$post_type) {
                throw new \Exception(__('Post type not found', 'voxel-toolkit'), 100);
            }

            if (!$post_type->is_managed_by_voxel()) {
                throw new \Exception(__('Invalid request', 'voxel-toolkit'), 100);
            }

            // Get the field key - it might be filter key or field key
            $field_key = $_GET['field_key'] ?? '';

            // First try to find a filter with this key to get the actual source field
            $filter = $post_type->get_filter($field_key);

            if ($filter) {
                if ($filter->get_type() === 'relations') {
                    // Get the source from the filter's props
                    $source = $filter->get_prop('source');
                    if ($source && $source !== '(manual)') {
                        $field_key = $source;
                    }
                }
            }

            // Get the field
            $field = $post_type->get_field($field_key);

            if (!$field) {
                throw new \Exception(__('Field not found', 'voxel-toolkit'), 101);
            }

            if ($field->get_type() !== 'post-relation') {
                throw new \Exception(__('Invalid field type', 'voxel-toolkit'), 101);
            }

            // Get allowed post types from the field
            $related_post_types = $field->get_prop('post_types');
            $post_types = [];

            if (is_array($related_post_types)) {
                foreach ($related_post_types as $pt_key) {
                    $pt = \Voxel\Post_Type::get($pt_key);
                    if ($pt && $pt->is_managed_by_voxel()) {
                        $post_types[] = $pt->get_key();
                    }
                }
            }

            if (empty($post_types)) {
                throw new \Exception(__('Invalid request', 'voxel-toolkit'), 102);
            }

            $author_id = absint(get_current_user_id());
            $offset = absint($_GET['offset'] ?? 0);
            $per_page = absint($_GET['per_page'] ?? 10);
            $post_id = $_GET['post_id'] ?? null;
            $limit = $post_id ? 1 : $per_page + 1;

            global $wpdb;

            // Handle exclusions
            $post__not_in = '';
            if (!empty($_GET['exclude'])) {
                $exclude_ids = explode(',', sanitize_text_field($_GET['exclude']));
                $exclude_ids = array_filter(array_map('absint', $exclude_ids));
                if (!empty($exclude_ids)) {
                    $post__not_in = sprintf('AND p.ID NOT IN (%s)', join(',', $exclude_ids));
                }
            }

            $query_post_types = '\'' . join('\',\'', array_map('esc_sql', $post_types)) . '\'';
            $query_order_by = 'p.post_title ASC';
            $query_search = '';
            $joins = [];

            // Handle search
            if (!empty($_GET['search'])) {
                $search_string = sanitize_text_field($_GET['search']);
                $search_string = \Voxel\prepare_keyword_search($search_string);
                if (!empty($search_string)) {
                    $search_string = esc_sql($search_string);
                    $query_search = "AND MATCH(p.post_title) AGAINST('{$search_string}' IN BOOLEAN MODE)";
                    $query_order_by = "MATCH(p.post_title) AGAINST('{$search_string}' IN BOOLEAN MODE) DESC";

                    // Also search in user display_name for profile post type
                    if (in_array('profile', $post_types, true)) {
                        $joins['users'] = "LEFT JOIN {$wpdb->users} u ON p.post_author = u.ID";
                        $query_search = <<<SQL
                            AND (
                                MATCH(p.post_title) AGAINST('{$search_string}' IN BOOLEAN MODE)
                                OR MATCH(u.display_name) AGAINST('{$search_string}' IN BOOLEAN MODE)
                            )
                        SQL;
                        $query_order_by = <<<SQL
                            MATCH(p.post_title) AGAINST('{$search_string}' IN BOOLEAN MODE) DESC,
                            MATCH(u.display_name) AGAINST('{$search_string}' IN BOOLEAN MODE) DESC
                        SQL;
                    }
                }
            }

            // Handle additional statuses
            $query_additional_statuses = '';
            if (!empty($field->get_prop('allowed_statuses'))) {
                $query_additional_statuses = "'" . join(
                    "','",
                    array_map('esc_sql', (array) $field->get_prop('allowed_statuses'))
                ) . "'";
            }

            $joins_sql = join(' ', array_unique($joins));
            $scope = $_GET['scope'] ?? 'any';
            $query_additional_post_id = '';
            if ($post_id) {
                $query_additional_post_id = "AND p.ID = " . absint($post_id);
            }

            // Build query based on scope
            if ($scope === 'any') {
                if (!empty($query_additional_statuses)) {
                    $sql = <<<SQL
                        SELECT p.ID FROM {$wpdb->posts} p {$joins_sql}
                        WHERE p.post_type IN ({$query_post_types})
                            AND p.post_status IN ('publish',{$query_additional_statuses})
                            {$post__not_in}
                            {$query_search}
                            {$query_additional_post_id}
                        ORDER BY {$query_order_by}
                        LIMIT {$limit} OFFSET {$offset}
                    SQL;
                } else {
                    $sql = <<<SQL
                        SELECT p.ID FROM {$wpdb->posts} p {$joins_sql}
                        WHERE p.post_status = 'publish'
                            AND p.post_type IN ({$query_post_types})
                            {$post__not_in}
                            {$query_search}
                            {$query_additional_post_id}
                        ORDER BY {$query_order_by}
                        LIMIT {$limit} OFFSET {$offset}
                    SQL;
                }
            } elseif ($scope === 'current_user') {
                if (!is_user_logged_in()) {
                    return wp_send_json([
                        'success' => true,
                        'has_more' => 0,
                        'data' => [],
                        'scope' => $scope,
                    ]);
                }

                if (!empty($query_additional_statuses)) {
                    $sql = <<<SQL
                        SELECT p.ID FROM {$wpdb->posts} p {$joins_sql}
                        WHERE p.post_author = {$author_id}
                            AND p.post_status IN ('publish',{$query_additional_statuses})
                            AND p.post_type IN ({$query_post_types})
                            {$post__not_in}
                            {$query_search}
                            {$query_additional_post_id}
                        ORDER BY {$query_order_by}
                        LIMIT {$limit} OFFSET {$offset}
                    SQL;
                } else {
                    $sql = <<<SQL
                        SELECT p.ID FROM {$wpdb->posts} p {$joins_sql}
                        WHERE p.post_author = {$author_id}
                            AND p.post_status = 'publish'
                            AND p.post_type IN ({$query_post_types})
                            {$post__not_in}
                            {$query_search}
                            {$query_additional_post_id}
                        ORDER BY {$query_order_by}
                        LIMIT {$limit} OFFSET {$offset}
                    SQL;
                }
            } else {
                // Default to 'any' scope
                $sql = <<<SQL
                    SELECT p.ID FROM {$wpdb->posts} p {$joins_sql}
                    WHERE p.post_status = 'publish'
                        AND p.post_type IN ({$query_post_types})
                        {$post__not_in}
                        {$query_search}
                        {$query_additional_post_id}
                    ORDER BY {$query_order_by}
                    LIMIT {$limit} OFFSET {$offset}
                SQL;
            }

            $post_ids = $wpdb->get_col($sql);
            $has_more = count($post_ids) > $per_page;
            if ($has_more) {
                array_pop($post_ids);
            }

            _prime_post_caches($post_ids);

            $posts = [];
            foreach ($post_ids as $pid) {
                if ($post = \Voxel\Post::get($pid)) {
                    $posts[$post->get_id()] = [
                        'id' => $post->get_id(),
                        'title' => $post->get_display_name(),
                        'logo' => $post->get_avatar_markup(),
                        'type' => $post->post_type->get_singular_name(),
                        'icon' => \Voxel\get_icon_markup($post->post_type->get_icon()),
                        'requires_approval' => $post->get_author_id() !== $author_id,
                    ];
                }
            }

            return wp_send_json([
                'success' => true,
                'has_more' => $has_more,
                'data' => $posts,
                'scope' => $scope,
            ]);
        } catch (\Throwable $e) {
            return wp_send_json([
                'success' => false,
                'message' => $e->getMessage(),
                'code' => $e->getCode(),
            ]);
        }
    }

    /**
     * Deinit - cleanup hooks when function is disabled
     */
    public function deinit() {
        remove_action('wp_enqueue_scripts', array($this, 'register_assets'));
        remove_action('admin_enqueue_scripts', array($this, 'register_assets'));
        remove_filter('elementor/widget/render_content', array($this, 'render_widget'), 10);
        remove_action('voxel_ajax_vt_relations_get_posts', array($this, 'get_posts_for_relations_filter'));
        remove_action('voxel_ajax_nopriv_vt_relations_get_posts', array($this, 'get_posts_for_relations_filter'));
        remove_action('elementor/frontend/widget/before_render', array($this, 'enqueue_assets_elementor_preview'), 999);
    }
}
