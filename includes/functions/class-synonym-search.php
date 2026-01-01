<?php
/**
 * Synonym Search - Add synonyms to taxonomy terms for enhanced keyword search
 *
 * Features:
 * - Adds "Synonyms" field to taxonomy term edit screens
 * - AI-powered synonym generation via configured AI provider
 * - Extends Voxel's keywords filter to include synonyms in search indexing
 * - Requires re-indexing posts after adding/editing synonyms
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Synonym_Search {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Meta key for storing synonyms
     */
    const META_KEY = 'vt_synonyms';

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
        // Add synonyms field to all taxonomy term forms
        // Call directly since we're instantiated after init has started
        $this->setup_taxonomy_fields();

        // Add synonyms column to taxonomy list tables
        $this->setup_taxonomy_columns();

        // Enqueue admin scripts
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));

        // AJAX handlers
        add_action('wp_ajax_vt_generate_synonyms', array($this, 'ajax_generate_synonyms'));
        add_action('wp_ajax_vt_save_synonyms', array($this, 'ajax_save_synonyms'));
        add_action('wp_ajax_vt_bulk_generate_synonyms', array($this, 'ajax_bulk_generate_synonyms'));
    }

    /**
     * Setup taxonomy fields for all Voxel taxonomies
     */
    public function setup_taxonomy_fields() {
        // Get all registered taxonomies
        $taxonomies = get_taxonomies(array('public' => true), 'names');

        foreach ($taxonomies as $taxonomy) {
            // Add field to "Add New Term" form
            add_action("{$taxonomy}_add_form_fields", array($this, 'add_synonyms_field'));

            // Add field to "Edit Term" form
            add_action("{$taxonomy}_edit_form_fields", array($this, 'edit_synonyms_field'), 10, 2);

            // Save the field value
            add_action("created_{$taxonomy}", array($this, 'save_synonyms_field'));
            add_action("edited_{$taxonomy}", array($this, 'save_synonyms_field'));
        }
    }

    /**
     * Setup taxonomy columns for all taxonomies
     */
    public function setup_taxonomy_columns() {
        // Get all registered taxonomies
        $taxonomies = get_taxonomies(array('public' => true), 'names');

        foreach ($taxonomies as $taxonomy) {
            // Add column header
            add_filter("manage_edit-{$taxonomy}_columns", array($this, 'add_synonyms_column'));

            // Populate column content
            add_filter("manage_{$taxonomy}_custom_column", array($this, 'render_synonyms_column'), 10, 3);
        }
    }

    /**
     * Add synonyms column to taxonomy list table
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function add_synonyms_column($columns) {
        // Insert after description or at the end
        $new_columns = array();
        foreach ($columns as $key => $label) {
            $new_columns[$key] = $label;
            if ($key === 'description') {
                $new_columns['vt_synonyms'] = __('Synonyms', 'voxel-toolkit');
            }
        }

        // If description column doesn't exist, add at end (before posts count)
        if (!isset($new_columns['vt_synonyms'])) {
            $posts_column = isset($new_columns['posts']) ? $new_columns['posts'] : null;
            unset($new_columns['posts']);
            $new_columns['vt_synonyms'] = __('Synonyms', 'voxel-toolkit');
            if ($posts_column) {
                $new_columns['posts'] = $posts_column;
            }
        }

        return $new_columns;
    }

    /**
     * Render synonyms column content
     *
     * @param string $content     Column content
     * @param string $column_name Column name
     * @param int    $term_id     Term ID
     * @return string Column content
     */
    public function render_synonyms_column($content, $column_name, $term_id) {
        if ($column_name !== 'vt_synonyms') {
            return $content;
        }

        $synonyms = get_term_meta($term_id, self::META_KEY, true);

        if (empty($synonyms)) {
            return '<span style="color: #999;">—</span>';
        }

        // Truncate if too long
        $max_length = 50;
        if (strlen($synonyms) > $max_length) {
            $truncated = substr($synonyms, 0, $max_length) . '...';
            return '<span title="' . esc_attr($synonyms) . '">' . esc_html($truncated) . '</span>';
        }

        return esc_html($synonyms);
    }

    /**
     * Add synonyms field to "Add New Term" form
     *
     * @param string $taxonomy Taxonomy slug
     */
    public function add_synonyms_field($taxonomy) {
        ?>
        <div class="form-field term-synonyms-wrap">
            <label for="vt_synonyms"><?php esc_html_e('Synonyms', 'voxel-toolkit'); ?></label>
            <textarea name="vt_synonyms" id="vt_synonyms" rows="3" cols="40"></textarea>
            <p class="description">
                <?php esc_html_e('Enter synonyms separated by commas. These will be included in keyword searches. Posts are automatically re-indexed when saved.', 'voxel-toolkit'); ?>
            </p>
            <?php $this->render_ai_button(); ?>
        </div>
        <?php
    }

    /**
     * Add synonyms field to "Edit Term" form
     *
     * @param WP_Term $term     Current term object
     * @param string  $taxonomy Taxonomy slug
     */
    public function edit_synonyms_field($term, $taxonomy) {
        $synonyms = get_term_meta($term->term_id, self::META_KEY, true);
        ?>
        <tr class="form-field term-synonyms-wrap">
            <th scope="row">
                <label for="vt_synonyms"><?php esc_html_e('Synonyms', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <textarea name="vt_synonyms" id="vt_synonyms" rows="3" cols="50"><?php echo esc_textarea($synonyms); ?></textarea>
                <p class="description">
                    <?php esc_html_e('Enter synonyms separated by commas. These will be included in keyword searches. Posts are automatically re-indexed when saved.', 'voxel-toolkit'); ?>
                </p>
                <?php $this->render_ai_button($term->term_id, $term->name); ?>
            </td>
        </tr>
        <?php
    }

    /**
     * Render AI generate button
     *
     * @param int    $term_id   Term ID (optional, for edit form)
     * @param string $term_name Term name (optional, for edit form)
     */
    private function render_ai_button($term_id = 0, $term_name = '') {
        // Check if AI is configured
        if (!class_exists('Voxel_Toolkit_AI_Settings')) {
            return;
        }

        $ai_settings = Voxel_Toolkit_AI_Settings::instance();
        if (!$ai_settings->is_configured()) {
            ?>
            <p class="vt-ai-not-configured" style="color: #999; font-style: italic; margin-top: 8px;">
                <?php
                printf(
                    /* translators: %s: link to AI settings */
                    esc_html__('AI synonym generation available. %s to enable.', 'voxel-toolkit'),
                    '<a href="' . esc_url(admin_url('admin.php?page=voxel-toolkit')) . '">' . esc_html__('Configure AI Settings', 'voxel-toolkit') . '</a>'
                );
                ?>
            </p>
            <?php
            return;
        }

        // Get function settings for synonym count
        $settings = Voxel_Toolkit_Settings::instance()->get_function_settings('synonym_search', array(
            'synonym_count' => 5,
        ));
        $synonym_count = isset($settings['synonym_count']) ? intval($settings['synonym_count']) : 5;
        ?>
        <div class="vt-ai-synonyms-wrapper" style="margin-top: 10px;">
            <button type="button"
                    class="button vt-generate-synonyms-btn"
                    data-term-id="<?php echo esc_attr($term_id); ?>"
                    data-term-name="<?php echo esc_attr($term_name); ?>"
                    data-count="<?php echo esc_attr($synonym_count); ?>">
                <span class="dashicons dashicons-admin-generic" style="vertical-align: middle; margin-right: 4px;"></span>
                <?php esc_html_e('Generate Synonyms with AI', 'voxel-toolkit'); ?>
            </button>
            <span class="spinner" style="float: none; margin-top: 0;"></span>
            <span class="vt-ai-status" style="margin-left: 10px; color: #666;"></span>
        </div>
        <?php
    }

    /**
     * Save synonyms field value and trigger re-indexing
     *
     * @param int $term_id Term ID
     */
    public function save_synonyms_field($term_id) {
        if (!isset($_POST['vt_synonyms'])) {
            return;
        }

        // Get old value to check if it changed
        $old_synonyms = get_term_meta($term_id, self::META_KEY, true);
        $new_synonyms = sanitize_textarea_field($_POST['vt_synonyms']);

        // Save the new value
        update_term_meta($term_id, self::META_KEY, $new_synonyms);

        // Only re-index if synonyms actually changed
        if ($old_synonyms !== $new_synonyms) {
            $this->reindex_posts_by_term($term_id);
        }
    }

    /**
     * Re-index all posts that have a specific term
     *
     * @param int $term_id Term ID
     */
    protected function reindex_posts_by_term($term_id) {
        // Check if Voxel classes are available
        if (!class_exists('\Voxel\Post_Type') || !class_exists('\Voxel\Post')) {
            return;
        }

        $term = get_term($term_id);
        if (!$term || is_wp_error($term)) {
            return;
        }

        // Get all posts with this term
        $post_ids = get_posts(array(
            'post_type' => 'any',
            'post_status' => array('publish', 'pending', 'draft'),
            'numberposts' => -1,
            'fields' => 'ids',
            'tax_query' => array(
                array(
                    'taxonomy' => $term->taxonomy,
                    'field' => 'term_id',
                    'terms' => $term_id,
                ),
            ),
        ));

        if (empty($post_ids)) {
            return;
        }

        // Group posts by post type
        $posts_by_type = array();
        foreach ($post_ids as $post_id) {
            $post_type = get_post_type($post_id);
            if (!isset($posts_by_type[$post_type])) {
                $posts_by_type[$post_type] = array();
            }
            $posts_by_type[$post_type][] = $post_id;
        }

        // Re-index each post type's posts
        foreach ($posts_by_type as $post_type_key => $type_post_ids) {
            $post_type = \Voxel\Post_Type::get($post_type_key);
            if (!$post_type) {
                continue;
            }

            // Check if index table exists
            $index_table = $post_type->get_index_table();
            if (!$index_table || !$index_table->exists()) {
                continue;
            }

            // Re-index in batches to avoid memory issues
            $batch_size = 50;
            $batches = array_chunk($type_post_ids, $batch_size);

            foreach ($batches as $batch) {
                $index_table->index($batch);
            }
        }
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page hook
     */
    public function enqueue_admin_scripts($hook) {
        // Only on term edit pages
        if (!in_array($hook, array('term.php', 'edit-tags.php'))) {
            return;
        }

        wp_enqueue_script(
            'vt-synonym-search-admin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/synonym-search-admin.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_localize_script('vt-synonym-search-admin', 'vtSynonymSearch', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vt_synonym_search'),
            'strings' => array(
                'generating' => __('Generating synonyms...', 'voxel-toolkit'),
                'generated' => __('Synonyms generated!', 'voxel-toolkit'),
                'error' => __('Error generating synonyms', 'voxel-toolkit'),
                'noTermName' => __('Please enter a term name first', 'voxel-toolkit'),
            ),
        ));

        // Admin styles
        wp_add_inline_style('common', '
            .vt-generate-synonyms-btn {
                display: inline-flex !important;
                align-items: center;
            }
            .vt-generate-synonyms-btn .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
            .vt-ai-synonyms-wrapper .spinner.is-active {
                visibility: visible;
            }
        ');
    }

    /**
     * AJAX handler for generating synonyms
     */
    public function ajax_generate_synonyms() {
        check_ajax_referer('vt_synonym_search', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'voxel-toolkit')));
        }

        $term_name = isset($_POST['term_name']) ? sanitize_text_field($_POST['term_name']) : '';
        $count = isset($_POST['count']) ? intval($_POST['count']) : 5;
        $existing = isset($_POST['existing']) ? sanitize_textarea_field($_POST['existing']) : '';

        if (empty($term_name)) {
            wp_send_json_error(array('message' => __('Term name is required.', 'voxel-toolkit')));
        }

        // Get AI settings
        if (!class_exists('Voxel_Toolkit_AI_Settings')) {
            wp_send_json_error(array('message' => __('AI Settings not available.', 'voxel-toolkit')));
        }

        $ai_settings = Voxel_Toolkit_AI_Settings::instance();
        if (!$ai_settings->is_configured()) {
            wp_send_json_error(array('message' => __('AI is not configured. Please set up AI Settings first.', 'voxel-toolkit')));
        }

        // Build the prompt
        $prompt = sprintf(
            'Generate exactly %d synonyms, alternative terms, and related phrases for the term "%s". These synonyms will be used for search functionality, so include common variations, abbreviations, and related terms that users might search for.

Return ONLY the synonyms as a comma-separated list, nothing else. Do not include the original term. Do not include numbering or explanations.',
            $count,
            $term_name
        );

        // If there are existing synonyms, ask to add more
        if (!empty($existing)) {
            $prompt .= sprintf(
                "\n\nExisting synonyms (do not repeat these): %s",
                $existing
            );
        }

        $system_message = 'You are a helpful assistant that generates search synonyms. Respond only with comma-separated synonyms, no explanations or formatting.';

        // Generate via AI
        $result = $ai_settings->generate_completion($prompt, 200, 0.7, $system_message);

        if (is_wp_error($result)) {
            wp_send_json_error(array('message' => $result->get_error_message()));
        }

        // Clean up the result
        $synonyms = trim($result);
        // Remove any quotes or extra formatting
        $synonyms = str_replace(array('"', "'"), '', $synonyms);
        // Remove any trailing periods
        $synonyms = rtrim($synonyms, '.');

        // If there are existing synonyms, append the new ones
        if (!empty($existing)) {
            $existing_array = array_map('trim', explode(',', $existing));
            $new_array = array_map('trim', explode(',', $synonyms));
            $merged = array_unique(array_merge($existing_array, $new_array));
            $synonyms = implode(', ', $merged);
        }

        wp_send_json_success(array(
            'synonyms' => $synonyms,
        ));
    }

    /**
     * AJAX handler for saving synonyms (for quick save without full form submit)
     */
    public function ajax_save_synonyms() {
        check_ajax_referer('vt_synonym_search', 'nonce');

        if (!current_user_can('manage_categories')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'voxel-toolkit')));
        }

        $term_id = isset($_POST['term_id']) ? intval($_POST['term_id']) : 0;
        $synonyms = isset($_POST['synonyms']) ? sanitize_textarea_field($_POST['synonyms']) : '';

        if (!$term_id) {
            wp_send_json_error(array('message' => __('Term ID is required.', 'voxel-toolkit')));
        }

        update_term_meta($term_id, self::META_KEY, $synonyms);

        wp_send_json_success(array(
            'message' => __('Synonyms saved. Remember to re-index posts for changes to take effect.', 'voxel-toolkit'),
        ));
    }

    /**
     * AJAX handler for bulk generating synonyms
     */
    public function ajax_bulk_generate_synonyms() {
        check_ajax_referer('vt_bulk_synonyms', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Permission denied.', 'voxel-toolkit')));
        }

        $taxonomy = isset($_POST['taxonomy']) ? sanitize_text_field($_POST['taxonomy']) : '';
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
        $skip_existing = isset($_POST['skip_existing']) ? (bool) $_POST['skip_existing'] : true;
        $count = isset($_POST['count']) ? intval($_POST['count']) : 5;

        if (empty($taxonomy) || !taxonomy_exists($taxonomy)) {
            wp_send_json_error(array('message' => __('Invalid taxonomy.', 'voxel-toolkit')));
        }

        // Get AI settings
        if (!class_exists('Voxel_Toolkit_AI_Settings')) {
            wp_send_json_error(array('message' => __('AI Settings not available.', 'voxel-toolkit')));
        }

        $ai_settings = Voxel_Toolkit_AI_Settings::instance();
        if (!$ai_settings->is_configured()) {
            wp_send_json_error(array('message' => __('AI is not configured.', 'voxel-toolkit')));
        }

        // Get all terms for this taxonomy
        $all_terms = get_terms(array(
            'taxonomy' => $taxonomy,
            'hide_empty' => false,
            'orderby' => 'name',
            'order' => 'ASC',
        ));

        if (is_wp_error($all_terms) || empty($all_terms)) {
            wp_send_json_error(array('message' => __('No terms found in this taxonomy.', 'voxel-toolkit')));
        }

        $total = count($all_terms);

        // Get term at current offset
        if ($offset >= $total) {
            // We're done - get stats from transient
            $stats = get_transient('vt_bulk_synonyms_stats');
            delete_transient('vt_bulk_synonyms_stats');

            wp_send_json_success(array(
                'has_more' => false,
                'processed' => $total,
                'total' => $total,
                'offset' => $offset,
                'current_term' => '',
                'generated' => $stats['generated'] ?? 0,
                'skipped' => $stats['skipped'] ?? 0,
            ));
        }

        // Initialize stats on first run
        if ($offset === 0) {
            set_transient('vt_bulk_synonyms_stats', array('generated' => 0, 'skipped' => 0), HOUR_IN_SECONDS);
        }

        $stats = get_transient('vt_bulk_synonyms_stats') ?: array('generated' => 0, 'skipped' => 0);

        $term = $all_terms[$offset];
        $term_name = $term->name;
        $term_id = $term->term_id;

        // Check if term already has synonyms
        $existing_synonyms = get_term_meta($term_id, self::META_KEY, true);

        if ($skip_existing && !empty($existing_synonyms)) {
            // Skip this term
            $stats['skipped']++;
            set_transient('vt_bulk_synonyms_stats', $stats, HOUR_IN_SECONDS);

            wp_send_json_success(array(
                'has_more' => ($offset + 1) < $total,
                'processed' => $offset + 1,
                'total' => $total,
                'offset' => $offset + 1,
                'current_term' => $term_name . ' ' . __('(skipped)', 'voxel-toolkit'),
                'generated' => $stats['generated'],
                'skipped' => $stats['skipped'],
            ));
        }

        // Generate synonyms for this term
        $prompt = sprintf(
            'Generate exactly %d synonyms, alternative terms, and related phrases for the term "%s". These synonyms will be used for search functionality, so include common variations, abbreviations, and related terms that users might search for.

Return ONLY the synonyms as a comma-separated list, nothing else. Do not include the original term. Do not include numbering or explanations.',
            $count,
            $term_name
        );

        $system_message = 'You are a helpful assistant that generates search synonyms. Respond only with comma-separated synonyms, no explanations or formatting.';

        $result = $ai_settings->generate_completion($prompt, 200, 0.7, $system_message);

        if (is_wp_error($result)) {
            // Log error but continue with next term
            $stats['skipped']++;
            set_transient('vt_bulk_synonyms_stats', $stats, HOUR_IN_SECONDS);

            wp_send_json_success(array(
                'has_more' => ($offset + 1) < $total,
                'processed' => $offset + 1,
                'total' => $total,
                'offset' => $offset + 1,
                'current_term' => $term_name . ' ' . __('(error)', 'voxel-toolkit'),
                'generated' => $stats['generated'],
                'skipped' => $stats['skipped'],
            ));
        }

        // Clean up the result
        $synonyms = trim($result);
        $synonyms = str_replace(array('"', "'"), '', $synonyms);
        $synonyms = rtrim($synonyms, '.');

        // Save synonyms
        update_term_meta($term_id, self::META_KEY, $synonyms);

        // Re-index posts with this term
        $this->reindex_posts_by_term($term_id);

        $stats['generated']++;
        set_transient('vt_bulk_synonyms_stats', $stats, HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'has_more' => ($offset + 1) < $total,
            'processed' => $offset + 1,
            'total' => $total,
            'offset' => $offset + 1,
            'current_term' => $term_name,
            'generated' => $stats['generated'],
            'skipped' => $stats['skipped'],
        ));
    }

    /**
     * Get synonyms for a term
     *
     * @param int $term_id Term ID
     * @return string Synonyms string (comma-separated)
     */
    public static function get_synonyms($term_id) {
        return get_term_meta($term_id, self::META_KEY, true);
    }

    /**
     * Get synonyms as array
     *
     * @param int $term_id Term ID
     * @return array Synonyms array
     */
    public static function get_synonyms_array($term_id) {
        $synonyms = self::get_synonyms($term_id);
        if (empty($synonyms)) {
            return array();
        }
        return array_map('trim', explode(',', $synonyms));
    }
}
