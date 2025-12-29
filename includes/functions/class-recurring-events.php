<?php
/**
 * Recurring Events - Multiple Instances
 *
 * Shows recurring events multiple times in archives (once per occurrence)
 * Each card displays only its specific occurrence date
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Recurring_Events {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Track current render position and instance data
     */
    private static $instance_queue = [];
    private static $render_index = 0;
    private static $is_expanding = false;

    /**
     * Store occurrence data for initial page load
     */
    private static $initial_load_data = [];

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
    public function __construct() {
        if (!$this->is_enabled()) {
            return;
        }

        // Override search AJAX handler (priority 5, before Voxel's default 10)
        add_action('voxel_ajax_search_posts', [$this, 'search_posts_handler'], 5);
        add_action('voxel_ajax_nopriv_search_posts', [$this, 'search_posts_handler'], 5);

        // Enqueue frontend assets
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Handle initial page load (non-AJAX)
        add_action('voxel/before_render_search_results', [$this, 'capture_initial_render_ids']);
        add_action('wp_footer', [$this, 'output_occurrence_data'], 100);

        // Register AJAX endpoint for fetching occurrence data
        add_action('wp_ajax_vt_get_occurrences', [$this, 'ajax_get_occurrences']);
        add_action('wp_ajax_nopriv_vt_get_occurrences', [$this, 'ajax_get_occurrences']);
    }

    /**
     * Check if feature is enabled
     */
    private function is_enabled() {
        if (!class_exists('Voxel_Toolkit_Settings')) {
            return false;
        }
        return Voxel_Toolkit_Settings::instance()->is_function_enabled('recurring_events');
    }

    /**
     * Enqueue frontend assets
     */
    public function enqueue_assets() {
        wp_enqueue_script(
            'vt-recurring-events',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/recurring-events.js',
            [],
            VOXEL_TOOLKIT_VERSION,
            true
        );

        // Pass AJAX config to JavaScript
        wp_localize_script('vt-recurring-events', 'vtRecurringEventsConfig', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_recurring_events'),
        ]);

        wp_enqueue_style(
            'vt-recurring-events',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/recurring-events.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Capture IDs during initial page render (non-AJAX)
     */
    public function capture_initial_render_ids() {
        // Skip if this is an AJAX request (handled by search_posts_handler)
        if (wp_doing_ajax()) {
            return;
        }

        // Get IDs being rendered
        $ids = $GLOBALS['vx_preview_card_current_ids'] ?? [];

        if (empty($ids)) {
            return;
        }

        // Build occurrence data for each post
        foreach ($ids as $post_id) {
            if (isset(self::$initial_load_data[$post_id])) {
                continue; // Already processed
            }

            $post = \Voxel\Post::get($post_id);
            if (!$post) {
                continue;
            }

            $recurring_field = $this->get_recurring_date_field($post);
            if (!$recurring_field) {
                continue;
            }

            $upcoming = $recurring_field->get_upcoming();
            if (count($upcoming) <= 1) {
                continue; // Single or no occurrences, no expansion needed
            }

            // Store occurrence data
            self::$initial_load_data[$post_id] = [
                'occurrences' => array_map(function($occ, $index) {
                    return [
                        'index' => $index,
                        'start' => $occ['start'] ?? null,
                        'end' => $occ['end'] ?? null,
                    ];
                }, $upcoming, array_keys($upcoming)),
            ];
        }
    }

    /**
     * Output occurrence data as JSON in footer for JS to process
     */
    public function output_occurrence_data() {
        if (empty(self::$initial_load_data)) {
            return;
        }

        ?>
        <script type="text/javascript">
            window.vtRecurringEventsData = <?php echo wp_json_encode(self::$initial_load_data); ?>;
        </script>
        <?php
    }

    /**
     * AJAX handler to get occurrence data for given post IDs
     */
    public function ajax_get_occurrences() {
        $post_ids = isset($_POST['post_ids']) ? array_map('absint', (array)$_POST['post_ids']) : [];

        if (empty($post_ids)) {
            wp_send_json_success(['data' => []]);
        }

        $result = [];

        foreach ($post_ids as $post_id) {
            $post = \Voxel\Post::get($post_id);
            if (!$post) {
                continue;
            }

            $recurring_field = $this->get_recurring_date_field($post);
            if (!$recurring_field) {
                continue;
            }

            $upcoming = $recurring_field->get_upcoming();
            if (count($upcoming) <= 1) {
                continue; // Single or no occurrences, no expansion needed
            }

            $result[$post_id] = [
                'occurrences' => array_map(function($occ, $index) {
                    return [
                        'index' => $index,
                        'start' => $occ['start'] ?? null,
                        'end' => $occ['end'] ?? null,
                    ];
                }, $upcoming, array_keys($upcoming)),
            ];
        }

        wp_send_json_success(['data' => $result]);
    }

    /**
     * Override search AJAX handler to expand recurring events
     */
    public function search_posts_handler() {
        // Prevent Voxel's handler from running
        remove_all_actions('voxel_ajax_search_posts');
        remove_all_actions('voxel_ajax_nopriv_search_posts');

        $limit = absint($_GET['limit'] ?? 10);
        $page = absint($_GET['pg'] ?? 1);
        $offset = absint($_GET['__offset'] ?? 0);
        $load_markers = (($_GET['__load_markers'] ?? null) === 'yes');
        $load_additional_markers = absint($_GET['__load_additional_markers'] ?? 0);
        $exclude = array_filter(array_map('absint', explode(',', (string)($_GET['__exclude'] ?? ''))));

        // Get original results (IDs only, no render)
        $original = \Voxel\get_search_results(wp_unslash($_GET), [
            'limit' => $limit,
            'offset' => $offset,
            'render' => false,
            'template_id' => is_numeric($_GET['__template_id'] ?? null) ? (int)$_GET['__template_id'] : null,
            'get_total_count' => !empty($_GET['__get_total_count']),
            'exclude' => array_slice($exclude, 0, 25),
            'apply_conditional_logic' => true,
        ]);

        // Expand IDs for recurring events
        $expanded_data = $this->expand_recurring_ids($original['ids']);
        $expanded_ids = $expanded_data['ids'];
        self::$instance_queue = $expanded_data['queue'];

        // Sort by occurrence start date
        $this->sort_by_occurrence_date($expanded_ids, self::$instance_queue);

        // Apply pagination to expanded results
        $paginated_ids = array_slice($expanded_ids, 0, $limit);
        $paginated_queue = array_slice(self::$instance_queue, 0, $limit);
        self::$instance_queue = $paginated_queue;

        // Setup instance tracking before render
        self::$render_index = 0;
        self::$is_expanding = true;

        // Render with expanded IDs
        $results = \Voxel\get_search_results(wp_unslash($_GET), [
            'ids' => $paginated_ids,
            'limit' => count($paginated_ids),
            'template_id' => is_numeric($_GET['__template_id'] ?? null) ? (int)$_GET['__template_id'] : null,
            'get_total_count' => false,
            'exclude' => [],
            'preload_additional_ids' => ($load_markers && $load_additional_markers && $page === 1) ? $load_additional_markers : 1,
            'render_cards_with_markers' => $load_markers,
            'apply_conditional_logic' => true,
        ]);

        // Handle additional markers
        if ($load_markers && $load_additional_markers && $page === 1 && !empty($results['additional_ids'])) {
            $additional_markers = \Voxel\get_search_results(wp_unslash($_GET), [
                'ids' => $results['additional_ids'],
                'render' => 'markers',
                'pg' => 1,
                'template_id' => null,
                'get_total_count' => false,
                'exclude' => array_slice($exclude, 0, 25),
                'apply_conditional_logic' => true,
            ]);
            echo '<div class="ts-additional-markers hidden">';
            echo $additional_markers['render'];
            echo '</div>';
        }

        // Inject occurrence data into rendered HTML
        $render = $this->inject_occurrence_data($results['render']);

        echo $results['styles'];
        echo $render;
        echo $results['scripts'];

        // Calculate pagination based on expanded count
        $total_expanded = count($expanded_ids);
        $has_next = $total_expanded > $limit;
        $has_prev = $offset > 0;
        $total_count = $original['total_count'] ?? $total_expanded;

        printf(
            '<script
                class="info"
                data-has-prev="%s"
                data-has-next="%s"
                data-has-results="%s"
                data-total-count="%d"
                data-display-count="%s"
                data-display-count-alt="%s"
            ></script>',
            $has_prev ? 'true' : 'false',
            $has_next ? 'true' : 'false',
            !empty($paginated_ids) ? 'true' : 'false',
            $total_count,
            \Voxel\count_format(count($paginated_ids), $total_count),
            \Voxel\count_format((($page - 1) * $limit) + count($paginated_ids), $total_count)
        );

        self::$is_expanding = false;
        exit;
    }

    /**
     * Expand IDs to include multiple instances per recurring event
     */
    private function expand_recurring_ids($post_ids) {
        $expanded_ids = [];
        $instance_queue = [];

        foreach ($post_ids as $post_id) {
            $post = \Voxel\Post::get($post_id);
            if (!$post) {
                continue;
            }

            $recurring_field = $this->get_recurring_date_field($post);

            if (!$recurring_field) {
                $expanded_ids[] = $post_id;
                $instance_queue[] = null;
                continue;
            }

            $upcoming = $recurring_field->get_upcoming();

            if (empty($upcoming) || count($upcoming) <= 1) {
                $expanded_ids[] = $post_id;
                $instance_queue[] = count($upcoming) === 1 ? [
                    'post_id' => $post_id,
                    'index' => 0,
                    'start' => $upcoming[0]['start'] ?? null,
                    'end' => $upcoming[0]['end'] ?? null,
                    'field_key' => $recurring_field->get_key(),
                ] : null;
                continue;
            }

            // Add entry for each occurrence
            foreach ($upcoming as $index => $occurrence) {
                $expanded_ids[] = $post_id;
                $instance_queue[] = [
                    'post_id' => $post_id,
                    'index' => $index,
                    'start' => $occurrence['start'] ?? null,
                    'end' => $occurrence['end'] ?? null,
                    'field_key' => $recurring_field->get_key(),
                ];
            }
        }

        return [
            'ids' => $expanded_ids,
            'queue' => $instance_queue,
        ];
    }

    /**
     * Sort expanded results by occurrence start date
     */
    private function sort_by_occurrence_date(&$ids, &$queue) {
        // Create combined array for sorting
        $combined = [];
        foreach ($ids as $i => $id) {
            $combined[] = [
                'id' => $id,
                'queue' => $queue[$i],
                'start' => $queue[$i]['start'] ?? '9999-12-31 23:59:59',
            ];
        }

        // Sort by start date
        usort($combined, function($a, $b) {
            return strcmp($a['start'], $b['start']);
        });

        // Extract sorted arrays
        $ids = array_column($combined, 'id');
        $queue = array_column($combined, 'queue');
    }

    /**
     * Get the first recurring-date field from a post
     */
    private function get_recurring_date_field($post) {
        if (!$post || !$post->post_type) {
            return null;
        }

        $fields = $post->post_type->get_fields();

        foreach ($fields as $field) {
            if ($field->get_type() === 'recurring-date') {
                // Get the field instance for this specific post
                return $post->get_field($field->get_key());
            }
        }

        return null;
    }

    /**
     * Inject occurrence data attributes into rendered cards
     */
    private function inject_occurrence_data($html) {
        if (empty(self::$instance_queue)) {
            return $html;
        }

        // Find all card wrappers and add data attributes
        $index = 0;
        $html = preg_replace_callback(
            '/<div class="ts-preview" data-post-id="(\d+)"/',
            function($matches) use (&$index) {
                $instance = self::$instance_queue[$index] ?? null;
                $index++;

                if (!$instance) {
                    return $matches[0];
                }

                $data_attr = sprintf(
                    ' data-vt-occurrence-index="%d" data-vt-occurrence-start="%s" data-vt-occurrence-end="%s"',
                    $instance['index'],
                    esc_attr($instance['start'] ?? ''),
                    esc_attr($instance['end'] ?? '')
                );

                return $matches[0] . $data_attr;
            },
            $html
        );

        return $html;
    }

    /**
     * Get current instance data during render (for potential future use)
     */
    public static function get_current_instance() {
        if (!self::$is_expanding) {
            return null;
        }
        return self::$instance_queue[self::$render_index] ?? null;
    }

    /**
     * Increment render index (called per card render)
     */
    public static function increment_render_index() {
        self::$render_index++;
    }
}
