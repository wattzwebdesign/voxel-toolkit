<?php
/**
 * Saved Search Function Class
 *
 * Main function class for the Saved Search feature.
 * Handles AJAX, database setup, Elementor injection, and notifications.
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Saved_Search {

    private static $instance = null;
    public static $table_version = '1.0';

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
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Load required files
     */
    private function load_dependencies() {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-saved-search-model.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-email-queue.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-email-batch-processor.php';

        // Initialize batch processor if batching is enabled
        $batch_settings = $this->get_email_batch_settings();
        if ($batch_settings['enabled']) {
            Voxel_Toolkit_Email_Batch_Processor::instance();
        }
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Database setup
        add_action('admin_init', array($this, 'maybe_setup_tables'));

        // AJAX handlers (vt_ prefix)
        add_action('voxel_ajax_vt_save_search', array($this, 'save_search'));
        add_action('voxel_ajax_vt_get_saved_searches', array($this, 'get_saved_searches'));
        add_action('voxel_ajax_vt_delete_saved_search', array($this, 'delete_saved_search'));
        add_action('voxel_ajax_vt_update_saved_search', array($this, 'update_saved_search'));

        // Expiration cleanup cron
        add_action('vt_saved_search_cleanup', array($this, 'cleanup_expired_searches'));
        $this->schedule_cleanup_cron();

        // Notification hooks (register after Voxel post types are ready)
        add_action('init', array($this, 'register_notification_hooks'), 100);

        // Fallback: WordPress post status transitions (for wp-admin posts)
        // Use high priority (9999) to ensure all post data is saved before we process
        add_action('transition_post_status', array($this, 'on_post_status_change'), 9999, 3);

        // Deferred notification processing (for wp-admin posts where meta saves after status change)
        add_action('vt_saved_search_deferred_notify', array($this, 'process_deferred_notification'), 10, 1);

        // Note: App events are registered early in main plugin file (voxel-toolkit.php)
        // to ensure they're added before Voxel caches events

        // Search form widget integration
        add_action('elementor/widget/render_content', array($this, 'render_save_button'), 10, 2);
        add_action('elementor/element/after_section_end', array($this, 'register_search_form_controls'), 10, 3);
        add_action('elementor/element/before_section_end', array($this, 'register_search_form_icon_controls'), 10, 3);

        // Enqueue assets in Elementor preview
        add_action('elementor/frontend/widget/before_render', array($this, 'enqueue_assets_elementor_iframe'), 999);

        // Register assets
        add_action('wp_enqueue_scripts', array($this, 'register_assets'), 5);

        // Register visibility rules
        add_filter('voxel/dynamic-data/visibility-rules', array($this, 'register_visibility_rules'));
    }

    /**
     * Register visibility rules with Voxel
     */
    public function register_visibility_rules($rules) {
        if (class_exists('\Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule')) {
            $rules['user:has_saved_searches'] = 'Voxel_Toolkit_User_Has_Saved_Searches_Rule';
        }
        return $rules;
    }

    /**
     * Maybe setup database tables
     */
    public function maybe_setup_tables() {
        $current_version = get_option('vt_saved_search_table_version', '');
        if ($current_version !== static::$table_version) {
            static::setup_tables();
        }

        // Setup email queue table if batching is enabled
        $batch_settings = $this->get_email_batch_settings();
        if ($batch_settings['enabled']) {
            Voxel_Toolkit_Email_Queue::maybe_setup_table();
        }
    }

    /**
     * Setup database tables
     */
    public static function setup_tables() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'vt_saved_searches';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            published_as bigint(20) unsigned DEFAULT NULL,
            notification tinyint(1) NOT NULL DEFAULT 1,
            details longtext NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY notification (notification)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('vt_saved_search_table_version', static::$table_version);
    }

    /**
     * Check if a user has any saved searches
     *
     * @param int $user_id User ID to check
     * @return bool True if user has saved searches, false otherwise
     */
    public static function user_has_saved_searches($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'vt_saved_searches';

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table_name} WHERE user_id = %d",
            $user_id
        ));

        return intval($count) > 0;
    }

    /**
     * Schedule cleanup cron job
     */
    public function schedule_cleanup_cron() {
        // Get expiration setting
        $settings = get_option('voxel_toolkit_options', array());
        $expiration = isset($settings['saved_search']['expiration']) ? $settings['saved_search']['expiration'] : 'never';

        $scheduled = wp_next_scheduled('vt_saved_search_cleanup');

        if ($expiration === 'never') {
            // Unschedule if expiration is disabled
            if ($scheduled) {
                wp_unschedule_event($scheduled, 'vt_saved_search_cleanup');
            }
            return;
        }

        // Schedule if not already scheduled
        if (!$scheduled) {
            wp_schedule_event(time(), 'daily', 'vt_saved_search_cleanup');
        }
    }

    /**
     * Cleanup expired saved searches
     */
    public function cleanup_expired_searches() {
        global $wpdb;

        // Get expiration setting
        $settings = get_option('voxel_toolkit_options', array());
        $expiration = isset($settings['saved_search']['expiration']) ? $settings['saved_search']['expiration'] : 'never';

        // If expiration is set to never, do nothing
        if ($expiration === 'never') {
            return;
        }

        // Calculate expiration date
        $days = absint($expiration);
        if ($days <= 0) {
            return;
        }

        $table_name = $wpdb->prefix . 'vt_saved_searches';
        $expiration_date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Delete expired searches
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$table_name} WHERE created_at < %s",
                $expiration_date
            )
        );
    }

    /**
     * Register assets
     */
    public function register_assets() {
        wp_register_script(
            'vt-save-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/save-search.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_register_style(
            'vt-save-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/save-search.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_register_script(
            'vt-load-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/load-search.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_register_style(
            'vt-load-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/load-search.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );

        wp_register_script(
            'vt-saved-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/saved-search.js',
            array('jquery'),
            VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_register_style(
            'vt-saved-search',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/saved-search.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Enqueue assets in Elementor iframe
     */
    public function enqueue_assets_elementor_iframe($widget) {
        if (function_exists('\Voxel\is_elementor_preview') && \Voxel\is_elementor_preview()) {
            wp_enqueue_script('vt-save-search');
            wp_enqueue_style('vt-save-search');
            wp_enqueue_script('vt-load-search');
            wp_enqueue_style('vt-load-search');
        }
    }

    /**
     * Register saved search events with Voxel
     * Note: This is now primarily handled in the main plugin file (voxel-toolkit.php)
     * via early filter registration to ensure events are added before Voxel caches them.
     */
    public function register_events($events) {
        if (!class_exists('\Voxel\Post_Type')) {
            return $events;
        }

        // Load event class
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-saved-search-event.php';

        foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
            $event = new Voxel_Toolkit_Saved_Search_Event($post_type);
            $events[$event->get_key()] = $event;
        }

        return $events;
    }

    /**
     * Register notification hooks after Voxel post types are ready
     */
    public function register_notification_hooks() {
        if (!class_exists('\Voxel\Post_Type')) {
            return;
        }

        foreach (\Voxel\Post_Type::get_voxel_types() as $post_type) {
            $hook_created = 'voxel/app-events/post-types/' . $post_type->get_key() . '/post:created';
            $hook_approved = 'voxel/app-events/post-types/' . $post_type->get_key() . '/post:approved';

            add_action($hook_created, array($this, 'handle_voxel_event'), 999, 1);
            add_action($hook_approved, array($this, 'handle_voxel_event'), 999, 1);
        }
    }

    /**
     * Handle Voxel app events (post:created, post:approved)
     * This is separate from the WordPress transition_post_status fallback
     */
    public function handle_voxel_event($event) {
        $this->debug_log('handle_voxel_event: Received Voxel app event');

        // Skip if we're already processing via deferred queue (transition_post_status)
        // The deferred queue will handle it at shutdown
        if (!empty($this->deferred_posts)) {
            $post_id = isset($event->post) ? $event->post->get_id() : 'unknown';
            $this->debug_log(sprintf('handle_voxel_event: Skipping post %s - deferred queue will handle it', $post_id));
            return;
        }

        $this->add_cron_event($event);
    }

    /**
     * Register search form icon controls
     */
    public function register_search_form_icon_controls($element, $section_id, $args) {
        if ('ts-search-form' !== $element->get_name()) {
            return;
        }
        if ('ts_ui_icons' !== $section_id) {
            return;
        }

        $element->add_control(
            'vt_ss_form_btn_save_icon',
            [
                'label' => __('Save Search icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_form_btn_icon',
            [
                'label' => __('Load Search icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'skin' => 'inline',
                'label_block' => false,
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );
    }

    /**
     * Register search form style controls
     */
    public function register_search_form_style_controls($element, $section_id, $args) {
        if ('ts-search-form' !== $element->get_name()) {
            return;
        }
        if ('ts_sf_styling_buttons' !== $section_id) {
            return;
        }

        $element->start_controls_section(
            'vt_ss_save_and_get_notification',
            [
                'label' => __('Saved Search (VT)', 'voxel-toolkit'),
                'tab' => 'tab_general',
            ]
        );

        $element->add_control(
            'vt_ss_save_search_items',
            [
                'label' => __('General', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $element->start_controls_tabs('vt_ss_save_button_tabs');

        // Normal tab
        $element->start_controls_tab(
            'vt_ss_save_button_normal',
            ['label' => __('Normal', 'voxel-toolkit')]
        );

        $element->add_responsive_control(
            'vt_ss_text_align',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'flex-end' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'center',
                'toggle' => true,
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'justify-content: {{VALUE}}!important;',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_icon_size',
            [
                'label' => __('Icon size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target i' => 'font-size: {{SIZE}}{{UNIT}}!important;',
                    '{{WRAPPER}} .vt_save_search .ts-popup-target svg' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}}!important;',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_height',
            [
                'label' => __('Button Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'height: {{SIZE}}{{UNIT}}!important;',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_radius',
            [
                'label' => __('Border radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1], '%' => ['min' => 0, 'max' => 100]],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'border-radius: {{SIZE}}{{UNIT}}!important;',
                ],
            ]
        );

        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'vt_ss_btn_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt_save_search .ts-popup-target',
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_color',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#000000',
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'color: {{VALUE}}!important',
                    '{{WRAPPER}} .vt_save_search .ts-popup-target svg' => 'fill: {{VALUE}}!important',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_bg',
            [
                'label' => __('Background color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search div.ts-popup-target' => 'background-color: {{VALUE}}!important',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_padding',
            [
                'label' => __('Button padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}!important;',
                ],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_margin',
            [
                'label' => __('Button margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}!important;',
                ],
            ]
        );

        $element->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'vt_ss_btn_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt_save_search .ts-popup-target',
            ]
        );

        $element->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'vt_ss_btn_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt_save_search .ts-popup-target',
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_icon_spacing',
            [
                'label' => __('Icon/Text spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => ['px' => ['min' => 0, 'max' => 100, 'step' => 1]],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search .ts-popup-target' => 'grid-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $element->end_controls_tab();

        // Hover tab
        $element->start_controls_tab(
            'vt_ss_save_button_hover',
            ['label' => __('Hover', 'voxel-toolkit')]
        );

        $element->add_control(
            'vt_ss_btn_color_hover',
            [
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search:hover .ts-popup-target' => 'color: {{VALUE}}!important',
                    '{{WRAPPER}} .vt_save_search:hover .ts-popup-target svg' => 'fill: {{VALUE}}!important',
                ],
            ]
        );

        $element->add_control(
            'vt_ss_btn_bg_hover',
            [
                'label' => __('Background color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search:hover .ts-popup-target' => 'background: {{VALUE}}!important',
                ],
            ]
        );

        $element->end_controls_tab();
        $element->end_controls_tabs();
        $element->end_controls_section();
    }

    /**
     * Register search form controls
     */
    public function register_search_form_controls($element, $section_id, $args) {
        if ('ts-search-form' !== $element->get_name()) {
            return;
        }
        if ('ts_sf_buttons' !== $section_id) {
            return;
        }

        $element->start_controls_section(
            'vt_ss_save_search_button',
            [
                'label' => __('Saved Search (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $element->add_control(
            'vt_ss_show_save_search_btn',
            [
                'label' => __('Enable', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'return_value' => 'yes',
            ]
        );

        $element->add_control(
            'vt_ss_success_message',
            [
                'label' => __('Success message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search saved successfully', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_link_label',
            [
                'label' => __('Go to saved page label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Your searches', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        // Top popup button heading
        $element->add_control(
            'vt_ss_top_popup_heading',
            [
                'label' => __('Top popup button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_top_popup_btn',
            [
                'label' => __('Show on desktop', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_top_popup_btn_tablet',
            [
                'label' => __('Show on tablet', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_top_popup_btn_mobile',
            [
                'label' => __('Show on mobile', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        // Save Search button heading
        $element->add_control(
            'vt_ss_main_btn_heading',
            [
                'label' => __('Save Search Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_main_btn',
            [
                'label' => __('Show on desktop', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_main_btn_tablet',
            [
                'label' => __('Show on tablet', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_show_main_btn_mobile',
            [
                'label' => __('Show on mobile', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_btn_text',
            [
                'label' => __('Button label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Save Search', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_responsive_control(
            'vt_ss_btn_width',
            [
                'label' => __('Button Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%', 'px', 'vw'],
                'default' => ['unit' => '%', 'size' => 100],
                'range' => [
                    '%' => ['min' => 0, 'max' => 100, 'step' => 1],
                    'px' => ['min' => 0, 'max' => 1000, 'step' => 1],
                    'vw' => ['min' => 0, 'max' => 100, 'step' => 1],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt_save_search' => 'width: {{SIZE}}{{UNIT}}!important;',
                ],
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        // Ask for title popup settings
        $element->add_control(
            'vt_ss_general_heading',
            [
                'label' => __('Ask for title popup', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_ask_for_title',
            [
                'label' => __('Enable', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'true',
                'return_value' => 'true',
                'condition' => ['vt_ss_show_save_search_btn' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ss_placeholder',
            [
                'label' => __('Input placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Leave a short note...', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => [
                    'vt_ss_ask_for_title' => 'true',
                    'vt_ss_show_save_search_btn' => 'yes',
                ],
            ]
        );

        // Load Search section
        $element->add_control(
            'vt_ls_heading',
            [
                'label' => __('Load Saved Search', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $element->add_control(
            'vt_ls_enable',
            [
                'label' => __('Enable', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'no',
                'return_value' => 'yes',
            ]
        );

        $element->add_control(
            'vt_ls_auto_apply',
            [
                'label' => __('Auto-apply on page load', 'voxel-toolkit'),
                'description' => __('Automatically load the last used saved search when user returns', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_btn_text',
            [
                'label' => __('Button label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Load Search', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_search_placeholder',
            [
                'label' => __('Search placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search saved...', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_clear_label',
            [
                'label' => __('Clear filters label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Clear filters', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_loaded_message',
            [
                'label' => __('Loaded message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Search loaded', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_empty_text',
            [
                'label' => __('Empty state text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No saved searches yet', 'voxel-toolkit'),
                'placeholder' => __('Type your text', 'voxel-toolkit'),
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        // Load Search - Top popup button
        $element->add_control(
            'vt_ls_top_popup_heading',
            [
                'label' => __('Top popup button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_top_popup_btn',
            [
                'label' => __('Show on desktop', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_top_popup_btn_tablet',
            [
                'label' => __('Show on tablet', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_top_popup_btn_mobile',
            [
                'label' => __('Show on mobile', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        // Load Search button heading
        $element->add_control(
            'vt_ls_main_btn_heading',
            [
                'label' => __('Load Search Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_main_btn',
            [
                'label' => __('Show on desktop', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_main_btn_tablet',
            [
                'label' => __('Show on tablet', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_show_main_btn_mobile',
            [
                'label' => __('Show on mobile', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
                'return_value' => 'yes',
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->add_control(
            'vt_ls_btn_width',
            [
                'label' => __('Button Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['%', 'px', 'vw'],
                'default' => ['unit' => '%', 'size' => 100],
                'range' => [
                    '%' => ['min' => 0, 'max' => 100, 'step' => 1],
                    'px' => ['min' => 0, 'max' => 1000, 'step' => 1],
                    'vw' => ['min' => 0, 'max' => 100, 'step' => 1],
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt_load_search' => 'width: {{SIZE}}{{UNIT}}!important;',
                ],
                'condition' => ['vt_ls_enable' => 'yes'],
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Render save search button on search form widget
     */
    public function render_save_button($widget_content, $widget) {
        if ($widget->get_name() !== 'ts-search-form') {
            return $widget_content;
        }

        $settings = $widget->get_settings_for_display();

        $save_icon = $widget->get_settings_for_display('vt_ss_form_btn_save_icon');
        $load_icon = $widget->get_settings_for_display('vt_ls_form_btn_icon');

        // Get saved search page from toolkit settings
        $function_settings = Voxel_Toolkit_Settings::instance()->get_function_settings('saved_search', array());
        $saved_search_page = isset($function_settings['saved_searches_page']) ? absint($function_settings['saved_searches_page']) : 0;

        // Default save search icon
        $default_save_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M20.137,24a2.8,2.8,0,0,1-1.987-.835L12,17.051,5.85,23.169a2.8,2.8,0,0,1-3.095.609A2.8,2.8,0,0,1,1,21.154V5A5,5,0,0,1,6,0H18a5,5,0,0,1,5,5V21.154a2.8,2.8,0,0,1-1.751,2.624A2.867,2.867,0,0,1,20.137,24ZM6,2A3,3,0,0,0,3,5V21.154a.843.843,0,0,0,1.437.6h0L11.3,14.933a1,1,0,0,1,1.41,0l6.855,6.819a.843.843,0,0,0,1.437-.6V5a3,3,0,0,0-3-3Z"/></svg>';

        // Default load search icon (folder/open icon)
        $default_load_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24"><path d="M19,2H5A5.006,5.006,0,0,0,0,7V17a5.006,5.006,0,0,0,5,5H19a5.006,5.006,0,0,0,5-5V7A5.006,5.006,0,0,0,19,2ZM5,4H19a3,3,0,0,1,3,3v.5H2V7A3,3,0,0,1,5,4ZM19,20H5a3,3,0,0,1-3-3V9.5H22V17A3,3,0,0,1,19,20Z"/></svg>';

        $config = [
            'widgetId' => $widget->get_id(),
            // Save Search config
            'enable' => $widget->get_settings_for_display('vt_ss_show_save_search_btn') === 'yes',
            'label' => $widget->get_settings_for_display('vt_ss_btn_text') ?: 'Save Search',
            'placeholder' => $widget->get_settings_for_display('vt_ss_placeholder') ?: 'Leave a short note...',
            'askForTitle' => $widget->get_settings_for_display('vt_ss_ask_for_title') === 'true',
            'icon' => $save_icon && !empty($save_icon['value']) ? \Voxel\get_icon_markup($save_icon) : $default_save_icon,
            'link' => $saved_search_page ? get_permalink($saved_search_page) : '#',
            'successMessage' => $widget->get_settings_for_display('vt_ss_success_message') ?: 'Search saved successfully.',
            'linkLabel' => $widget->get_settings_for_display('vt_ss_link_label') ?: 'Your searches',
            'showTopPopupButton' => [
                'desktop' => $widget->get_settings_for_display('vt_ss_show_top_popup_btn') === 'yes',
                'tablet' => $widget->get_settings_for_display('vt_ss_show_top_popup_btn_tablet') === 'yes',
                'mobile' => $widget->get_settings_for_display('vt_ss_show_top_popup_btn_mobile') === 'yes',
            ],
            'showMainButton' => [
                'desktop' => $widget->get_settings_for_display('vt_ss_show_main_btn') === 'yes',
                'tablet' => $widget->get_settings_for_display('vt_ss_show_main_btn_tablet') === 'yes',
                'mobile' => $widget->get_settings_for_display('vt_ss_show_main_btn_mobile') === 'yes',
            ],
            // Load Search config
            'enableLoadSearch' => $widget->get_settings_for_display('vt_ls_enable') === 'yes',
            'autoApply' => $widget->get_settings_for_display('vt_ls_auto_apply') === 'yes',
            'loadLabel' => $widget->get_settings_for_display('vt_ls_btn_text') ?: 'Load Search',
            'loadIcon' => $load_icon && !empty($load_icon['value']) ? \Voxel\get_icon_markup($load_icon) : $default_load_icon,
            'searchPlaceholder' => $widget->get_settings_for_display('vt_ls_search_placeholder') ?: 'Search saved...',
            'clearLabel' => $widget->get_settings_for_display('vt_ls_clear_label') ?: 'Clear filters',
            'loadedMessage' => $widget->get_settings_for_display('vt_ls_loaded_message') ?: 'Search loaded',
            'emptyText' => $widget->get_settings_for_display('vt_ls_empty_text') ?: 'No saved searches yet',
            'showLoadTopPopupButton' => [
                'desktop' => $widget->get_settings_for_display('vt_ls_show_top_popup_btn') === 'yes',
                'tablet' => $widget->get_settings_for_display('vt_ls_show_top_popup_btn_tablet') === 'yes',
                'mobile' => $widget->get_settings_for_display('vt_ls_show_top_popup_btn_mobile') === 'yes',
            ],
            'showLoadMainButton' => [
                'desktop' => $widget->get_settings_for_display('vt_ls_show_main_btn') === 'yes',
                'tablet' => $widget->get_settings_for_display('vt_ls_show_main_btn_tablet') === 'yes',
                'mobile' => $widget->get_settings_for_display('vt_ls_show_main_btn_mobile') === 'yes',
            ],
            // User state for hiding Load Search when no searches exist
            'userHasSearches' => is_user_logged_in() && self::user_has_saved_searches(),
        ];

        // Include Save Search template and assets
        if ($config['enable']) {
            $template_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/save-search-button.php';
            if (file_exists($template_path)) {
                include $template_path;
            }
            wp_enqueue_script('vt-save-search');
            wp_enqueue_style('vt-save-search');
        }

        // Include Load Search template and assets
        if ($config['enableLoadSearch']) {
            $template_path = VOXEL_TOOLKIT_PLUGIN_DIR . 'templates/saved-search/load-search-button.php';
            if (file_exists($template_path)) {
                include $template_path;
            }
            wp_enqueue_script('vt-load-search');
            wp_enqueue_style('vt-load-search');
        }

        ?>
        <script type="text/json" class="vtSavedSearchConfig">
            <?php echo wp_specialchars_decode(wp_json_encode($config)); ?>
        </script>
        <?php

        return $widget_content;
    }

    /**
     * Save a search (AJAX handler)
     */
    public function save_search() {
        try {
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to save a search', 'voxel-toolkit'));
            }

            $current_user = \Voxel\current_user();
            $post = function_exists('\Voxel\get_current_post') ? \Voxel\get_current_post() : null;

            $data = [
                'user_id' => $current_user->get_id(),
                'details' => isset($_POST['details']) ? $_POST['details'] : '',
                'title' => sanitize_text_field(isset($_POST['title']) ? $_POST['title'] : ''),
                'published_as' => $post ? $post->get_id() : null,
                'notification' => true,
            ];

            $search = Voxel_Toolkit_Saved_Search_Model::create($data);

            return wp_send_json([
                'success' => true,
                'message' => $search,
            ]);
        } catch (\Exception $e) {
            return wp_send_json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete a saved search (AJAX handler)
     */
    public function delete_saved_search() {
        try {
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in', 'voxel-toolkit'));
            }

            $search_id = absint(isset($_POST['search_id']) ? $_POST['search_id'] : 0);
            if (!$search_id) {
                throw new \Exception(__('Search ID is required', 'voxel-toolkit'));
            }

            $search = Voxel_Toolkit_Saved_Search_Model::get($search_id);
            if (!$search) {
                throw new \Exception(__('Search not found', 'voxel-toolkit'));
            }

            $current_user = \Voxel\current_user();
            if ($search->get_user_id() !== $current_user->get_id()) {
                throw new \Exception(__('You do not have permission to delete this search', 'voxel-toolkit'));
            }

            $search->delete();

            return wp_send_json([
                'success' => true,
                'message' => __('Deleted successfully', 'voxel-toolkit'),
            ]);
        } catch (\Exception $e) {
            return wp_send_json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Update a saved search (AJAX handler)
     */
    public function update_saved_search() {
        try {
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in', 'voxel-toolkit'));
            }

            $search_id = absint(isset($_POST['search_id']) ? $_POST['search_id'] : 0);
            if (!$search_id) {
                throw new \Exception(__('Search ID is required', 'voxel-toolkit'));
            }

            $data = isset($_POST['data']) ? $_POST['data'] : null;
            if (!$data) {
                throw new \Exception(__('Data cannot be empty', 'voxel-toolkit'));
            }

            $search = Voxel_Toolkit_Saved_Search_Model::get($search_id);
            if (!$search) {
                throw new \Exception(__('Search not found', 'voxel-toolkit'));
            }

            $current_user = \Voxel\current_user();
            if ($search->get_user_id() !== $current_user->get_id()) {
                throw new \Exception(__('You do not have permission to update this search', 'voxel-toolkit'));
            }

            $search->update($data);

            return wp_send_json([
                'success' => true,
                'message' => __('Updated successfully', 'voxel-toolkit'),
            ]);
        } catch (\Exception $e) {
            return wp_send_json([
                'success' => false,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get saved searches for current user (AJAX handler)
     */
    public function get_saved_searches() {
        $page = absint(isset($_GET['page']) ? $_GET['page'] : 1);
        $per_page = 10;

        $current_user = function_exists('\Voxel\current_user') ? \Voxel\current_user() : null;
        if (!$current_user) {
            return wp_send_json([
                'success' => false,
                'message' => __('You must be logged in to view saved searches', 'voxel-toolkit'),
            ]);
        }

        $user_id = $current_user->get_id();

        $args = [
            'limit' => $per_page + 1,
            'user_id' => $user_id,
            'order_by' => 'created_at',
            'order' => 'DESC',
        ];

        if ($page > 1) {
            $args['offset'] = ($page - 1) * $per_page;
        }

        $searches = Voxel_Toolkit_Saved_Search_Model::query($args);
        $has_more = count($searches) > $per_page;

        if ($has_more) {
            array_pop($searches);
        }

        $data = [];
        foreach ($searches as $search) {
            $data[$search->get_id()] = $search->get_saved_search_to_display();
        }

        return wp_send_json([
            'success' => true,
            'data' => $data,
            'has_more' => $has_more,
        ]);
    }

    /**
     * Store post IDs for deferred processing
     */
    private $deferred_posts = [];

    /**
     * Handle post status transitions (fallback for backend/wp-admin posts)
     */
    public function on_post_status_change($new_status, $old_status, $post) {
        if (!class_exists('\Voxel\Post_Type')) {
            $this->debug_log('on_post_status_change: Voxel Post_Type class not found');
            return;
        }

        $voxel_types = array_keys(\Voxel\Post_Type::get_voxel_types());
        if (!in_array($post->post_type, $voxel_types)) {
            return;
        }

        // Trigger on new publish or transition to publish
        if ($new_status === 'publish' && $old_status !== 'publish') {
            $this->debug_log(sprintf(
                'on_post_status_change: Post %d transitioning from %s to %s',
                $post->ID,
                $old_status,
                $new_status
            ));

            // Add to deferred processing queue
            // Processing at shutdown ensures all post data (meta, terms, etc.) is saved
            if (!in_array($post->ID, $this->deferred_posts)) {
                $this->deferred_posts[] = $post->ID;
                $this->debug_log(sprintf('on_post_status_change: Added post %d to deferred queue', $post->ID));

                // Register shutdown handler if not already registered
                static $shutdown_registered = false;
                if (!$shutdown_registered) {
                    add_action('shutdown', array($this, 'process_deferred_notifications'), 100);
                    $shutdown_registered = true;
                    $this->debug_log('on_post_status_change: Registered shutdown handler');
                }
            }
        }
    }

    /**
     * Process deferred notifications at shutdown
     * This ensures all post data is saved before we process
     */
    public function process_deferred_notifications() {
        if (empty($this->deferred_posts)) {
            return;
        }

        $this->debug_log(sprintf('process_deferred_notifications: Processing %d deferred posts', count($this->deferred_posts)));

        foreach ($this->deferred_posts as $post_id) {
            $this->debug_log(sprintf('process_deferred_notifications: Processing post %d', $post_id));
            $this->add_cron_event($post_id);
        }

        // Clear the queue
        $this->deferred_posts = [];
    }

    /**
     * Handle deferred notification processing (cron fallback)
     * This is kept as a fallback for scheduled events
     */
    public function process_deferred_notification($post_id) {
        $this->debug_log(sprintf('process_deferred_notification (cron): Processing post %d', $post_id));
        $this->add_cron_event($post_id);
    }

    /**
     * Debug logging helper
     * Only logs when WP_DEBUG and VT_SAVED_SEARCH_DEBUG are enabled
     */
    private function debug_log($message) {
        if (defined('WP_DEBUG') && WP_DEBUG && defined('VT_SAVED_SEARCH_DEBUG') && VT_SAVED_SEARCH_DEBUG) {
            if (function_exists('\Voxel\log')) {
                \Voxel\log('[VT Saved Search Debug] ' . $message);
            } else {
                error_log('[VT Saved Search Debug] ' . $message);
            }
        }
    }

    /**
     * Schedule cron event for notifications
     */
    public function add_cron_event($post_id) {
        // Handle Voxel event objects
        if (!is_numeric($post_id)) {
            $event = $post_id;
            if (!isset($event->post)) {
                $this->debug_log('add_cron_event: Event object missing post property');
                return;
            }
            $post = $event->post;
            if ($post->get_status() !== 'publish') {
                $this->debug_log(sprintf('add_cron_event: Post %d status is %s, not publish', $post->get_id(), $post->get_status()));
                return;
            }
            $post_id = $post->get_id();
            $this->debug_log(sprintf('add_cron_event: Received Voxel event for post %d', $post_id));
        } else {
            $this->debug_log(sprintf('add_cron_event: Received numeric post ID %d', $post_id));
        }

        // Check if we've already processed this post to prevent duplicate notifications
        $processed_key = 'vt_ss_processed_' . $post_id;
        if (get_transient($processed_key)) {
            $this->debug_log(sprintf('add_cron_event: Post %d already processed (transient exists)', $post_id));
            return;
        }

        // Mark as processed for 60 seconds to prevent duplicates
        set_transient($processed_key, true, 60);
        $this->debug_log(sprintf('add_cron_event: Set transient for post %d, calling send_notifications', $post_id));

        // Process notifications immediately
        $this->send_notifications($post_id);
    }

    /**
     * Send notifications for matching saved searches
     */
    public function send_notifications($post_id) {
        $this->debug_log(sprintf('send_notifications: Starting for post %d', $post_id));

        if (!class_exists('\Voxel\Post')) {
            $this->debug_log('send_notifications: Voxel Post class not found');
            return;
        }

        $post = \Voxel\Post::get($post_id);
        if (!$post) {
            $this->debug_log(sprintf('send_notifications: Could not get Voxel Post for ID %d', $post_id));
            return;
        }

        // Only process published posts
        if ($post->get_status() !== 'publish') {
            $this->debug_log(sprintf('send_notifications: Post %d status is %s, skipping', $post_id, $post->get_status()));
            return;
        }

        // Index the post to ensure it's searchable
        $this->debug_log(sprintf('send_notifications: Indexing post %d', $post_id));
        $post->index();
        $post_type = $post->post_type;

        if (!$post_type) {
            $this->debug_log(sprintf('send_notifications: Post %d has no post_type', $post_id));
            return;
        }

        $this->debug_log(sprintf('send_notifications: Post type is %s', $post_type->get_key()));

        // Query saved searches for this post type (excluding post author)
        $author_id = $post->get_author_id();
        $this->debug_log(sprintf('send_notifications: Querying saved searches for post_type=%s, excluding author=%d', $post_type->get_key(), $author_id));

        $saved_searches = Voxel_Toolkit_Saved_Search_Model::query([
            'limit' => PHP_INT_MAX,
            'user_id' => -(int) $author_id,
            'post_type' => $post_type->get_key(),
            'notification' => 1,
        ]);

        $this->debug_log(sprintf('send_notifications: Found %d saved searches with notifications enabled', count($saved_searches)));

        if (empty($saved_searches)) {
            $this->debug_log('send_notifications: No matching saved searches found');
            return;
        }

        // Load event classes
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-saved-search-event.php';
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/saved-search/class-saved-search-event-no-email.php';

        // Check if email batching is enabled
        $batch_settings = $this->get_email_batch_settings();
        $this->debug_log(sprintf('send_notifications: Email batching enabled=%s', $batch_settings['enabled'] ? 'yes' : 'no'));

        $matches_found = 0;
        $notifications_sent = 0;

        foreach ($saved_searches as $search) {
            if (!$search->get_notification()) {
                $this->debug_log(sprintf('send_notifications: Search %d has notifications disabled, skipping', $search->get_id()));
                continue;
            }

            try {
                $args = [];
                $search_details = $search->get_details();
                $ignore_filters = $search->get_ignore_filters();

                foreach ($post_type->get_filters() as $filter) {
                    if (isset($search_details[$filter->get_key()])) {
                        if (!in_array($filter->get_type(), $ignore_filters)) {
                            $args[$filter->get_key()] = $search_details[$filter->get_key()];
                        }
                    }
                }

                $this->debug_log(sprintf('send_notifications: Search %d (user %d) - checking with %d filter args', $search->get_id(), $search->get_user_id(), count($args)));

                $cb = function($query) use ($post_id) {
                    $query->where(sprintf(
                        '`%s`.post_id = %d',
                        $query->table->get_escaped_name(),
                        $post_id
                    ));
                };

                $match = $post_type->query($args, $cb);

                if (!empty($match)) {
                    $matches_found++;
                    $this->debug_log(sprintf('send_notifications: Post %d MATCHES search %d (user %d)', $post_id, $search->get_id(), $search->get_user_id()));

                    if ($batch_settings['enabled']) {
                        // BATCHED MODE: Dispatch in-app/SMS immediately, queue email
                        $event = new Voxel_Toolkit_Saved_Search_Event_No_Email($post_type);
                        $event->dispatch($post->get_id(), $search->get_user_id(), $search->get_id());

                        // Queue email for batch processing
                        $this->queue_email_notification($post, $search, $post_type);
                    } else {
                        // LEGACY MODE: Dispatch all notifications immediately
                        $event = new Voxel_Toolkit_Saved_Search_Event($post_type);
                        $event->dispatch($post->get_id(), $search->get_user_id(), $search->get_id());
                    }
                    $notifications_sent++;
                    $this->debug_log(sprintf('send_notifications: Dispatched notification for search %d', $search->get_id()));
                } else {
                    $this->debug_log(sprintf('send_notifications: Post %d does NOT match search %d', $post_id, $search->get_id()));
                }
            } catch (\Exception $e) {
                // Log error but continue processing other searches
                $error_msg = sprintf(
                    '[VT Saved Search] Error processing search %d: %s',
                    $search->get_id(),
                    $e->getMessage()
                );
                $this->debug_log($error_msg);
                if (function_exists('\Voxel\log')) {
                    \Voxel\log($error_msg);
                }
            }
        }

        $this->debug_log(sprintf('send_notifications: Complete - %d matches found, %d notifications sent', $matches_found, $notifications_sent));
    }

    /**
     * Get email batch settings
     *
     * @return array Settings array
     */
    private function get_email_batch_settings() {
        $options = get_option('voxel_toolkit_options', array());
        $saved_search = isset($options['saved_search']) ? $options['saved_search'] : array();

        return array(
            'enabled' => !empty($saved_search['email_batching_enabled']),
            'batch_size' => isset($saved_search['email_batch_size']) ? intval($saved_search['email_batch_size']) : 25,
            'batch_interval' => isset($saved_search['email_batch_interval']) ? intval($saved_search['email_batch_interval']) : 5,
        );
    }

    /**
     * Queue email notification for batch processing
     *
     * @param \Voxel\Post $post The post that matched
     * @param Voxel_Toolkit_Saved_Search_Model $search The saved search
     * @param \Voxel\Post_Type $post_type The post type
     */
    private function queue_email_notification($post, $search, $post_type) {
        // Get recipient user
        $recipient = \Voxel\User::get($search->get_user_id());
        if (!$recipient) {
            return;
        }

        // Check if user has email notifications enabled
        // (Respect Voxel's notification preferences)
        $email = $recipient->get_email();
        if (empty($email)) {
            return;
        }

        try {
            // Create event to prepare dynamic tags
            $event = new Voxel_Toolkit_Saved_Search_Event($post_type);
            $event->prepare($post->get_id(), $search->get_user_id(), $search->get_id());

            // Get email template from event notifications config
            $notifications = Voxel_Toolkit_Saved_Search_Event::notifications();
            $email_config = isset($notifications['notify-subscriber']['email'])
                ? $notifications['notify-subscriber']['email']
                : array();

            if (empty($email_config['subject']) || empty($email_config['message'])) {
                return;
            }

            // Render subject and message with dynamic tags
            $dynamic_tags = $event->dynamic_tags();
            $subject = \Voxel\render($email_config['subject'], $dynamic_tags);
            $message = \Voxel\render($email_config['message'], $dynamic_tags);

            // Queue the email
            Voxel_Toolkit_Email_Queue::queue(array(
                'recipient_email' => $email,
                'recipient_id' => $search->get_user_id(),
                'post_id' => $post->get_id(),
                'saved_search_id' => $search->get_id(),
                'post_type' => $post_type->get_key(),
                'subject' => $subject,
                'message' => $message,
            ));
        } catch (\Exception $e) {
            if (function_exists('\Voxel\log')) {
                \Voxel\log(sprintf(
                    '[VT Saved Search] Error queuing email for search %d: %s',
                    $search->get_id(),
                    $e->getMessage()
                ));
            }
        }
    }
}

/**
 * User Has Saved Searches Visibility Rule
 *
 * Checks if the current logged-in user has any saved searches
 */
if (class_exists('\Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule')) {

    class Voxel_Toolkit_User_Has_Saved_Searches_Rule extends \Voxel\Dynamic_Data\Visibility_Rules\Base_Visibility_Rule {

        public function get_type(): string {
            return 'user:has_saved_searches';
        }

        public function get_label(): string {
            return _x('User has saved searches', 'visibility rules', 'voxel-toolkit');
        }

        public function evaluate(): bool {
            if (!is_user_logged_in()) {
                return false;
            }

            return Voxel_Toolkit_Saved_Search::user_has_saved_searches();
        }
    }
}
