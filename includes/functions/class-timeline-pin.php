<?php
/**
 * Timeline Pin Function
 *
 * Allows post authors to pin timeline posts to the top of their post's timeline feed.
 *
 * @package Voxel_Toolkit
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Timeline_Pin {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Track which widgets have pin enabled
     */
    private $enabled_widget_ids = array();

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
        if (self::$instance !== null) {
            return;
        }
        self::$instance = $this;

        $this->init_hooks();
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register AJAX endpoint for pin toggle
        add_action('voxel_ajax_vt_timeline.pin_toggle', array($this, 'handle_pin_toggle'));

        // Enqueue frontend scripts
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        // Add Elementor controls to Timeline widget
        add_action('elementor/element/before_section_end', array($this, 'add_elementor_settings_control'), 10, 3);
        add_action('elementor/element/after_section_end', array($this, 'add_elementor_style_controls'), 10, 3);

        // Hook into widget render to collect settings
        add_action('elementor/frontend/widget/before_render', array($this, 'before_widget_render'));

        // Output widget config as inline script
        add_action('wp_footer', array($this, 'output_widget_config'), 5);
    }

    /**
     * Check widget settings before render
     */
    public function before_widget_render($widget) {
        if ($widget->get_name() !== 'ts-timeline') {
            return;
        }

        $settings = $widget->get_settings_for_display();

        // Check if pin is enabled for this widget
        if (!empty($settings['vt_enable_pin']) && $settings['vt_enable_pin'] === 'yes') {
            $this->enabled_widget_ids[$widget->get_id()] = array(
                'enabled' => true,
            );
        }
    }

    /**
     * Output config for widgets that have pin enabled
     */
    public function output_widget_config() {
        // Only output if we have widgets with pin enabled
        if (empty($this->enabled_widget_ids)) {
            return;
        }

        // Get current post ID and check if user can pin
        global $post;
        $post_id = isset($post->ID) ? $post->ID : 0;
        $can_pin = $this->can_user_pin($post_id);
        $pinned_status_id = $this->get_pinned_post($post_id);

        ?>
        <script type="text/javascript">
        window.vtTimelinePinConfig = {
            widgets: <?php echo wp_json_encode($this->enabled_widget_ids); ?>,
            postId: <?php echo intval($post_id); ?>,
            canPin: <?php echo $can_pin ? 'true' : 'false'; ?>,
            pinnedStatusId: <?php echo $pinned_status_id ? intval($pinned_status_id) : 'null'; ?>
        };
        </script>
        <?php
    }

    /**
     * Add pin toggle control to Elementor Timeline widget settings
     */
    public function add_elementor_settings_control($widget, $section_id, $args) {
        // Only add to Timeline widget's settings section
        if ($widget->get_name() !== 'ts-timeline') {
            return;
        }

        if ($section_id !== 'ts_timeline_settings') {
            return;
        }

        $widget->add_control(
            'vt_enable_pin',
            array(
                'label' => __('Enable Pin to Top (VT)', 'voxel-toolkit'),
                'description' => __('Allow post authors to pin timeline posts to the top.', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'separator' => 'before',
            )
        );
    }

    /**
     * Add style controls for pinned posts
     */
    public function add_elementor_style_controls($widget, $section_id, $args) {
        // Only add to Timeline widget
        if ($widget->get_name() !== 'ts-timeline') {
            return;
        }

        // Add after the last style section
        if ($section_id !== 'ts_status_post') {
            return;
        }

        // Pinned Post Style Section
        $widget->start_controls_section(
            'vt_pinned_post_style',
            array(
                'label' => __('Pinned Post (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array(
                    'vt_enable_pin' => 'yes',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vxf-post.vt-pinned' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vxf-post.vt-pinned' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_border_width',
            array(
                'label' => __('Border Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 5,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vxf-post.vt-pinned' => 'border-width: {{SIZE}}{{UNIT}}; border-style: solid;',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 20,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vxf-post.vt-pinned' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_badge_heading',
            array(
                'label' => __('Pinned Badge', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $widget->add_control(
            'vt_pinned_badge_bg',
            array(
                'label' => __('Badge Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-pinned-badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_badge_color',
            array(
                'label' => __('Badge Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-pinned-badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pin_icon_heading',
            array(
                'label' => __('Pin Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $widget->add_control(
            'vt_pin_icon_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-pin-icon' => 'color: {{VALUE}};',
                ),
            )
        );

        $widget->end_controls_section();
    }

    /**
     * Enqueue frontend scripts
     */
    public function enqueue_scripts() {
        $js_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/js/timeline-pin.js';
        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'assets/css/timeline-pin.css';

        wp_enqueue_script(
            'vt-timeline-pin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/js/timeline-pin.js',
            array('jquery'),
            file_exists($js_file) ? filemtime($js_file) : VOXEL_TOOLKIT_VERSION,
            true
        );

        wp_enqueue_style(
            'vt-timeline-pin',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/timeline-pin.css',
            array(),
            file_exists($css_file) ? filemtime($css_file) : VOXEL_TOOLKIT_VERSION
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script('vt-timeline-pin', 'vtTimelinePin', array(
            'ajaxUrl' => home_url('/?vx=1&action=vt_timeline.pin_toggle'),
            'nonce' => wp_create_nonce('vt_timeline_pin'),
            'i18n' => array(
                'pinToTop' => __('Pin to Top', 'voxel-toolkit'),
                'unpin' => __('Unpin', 'voxel-toolkit'),
                'pinned' => __('Pinned', 'voxel-toolkit'),
                'error' => __('An error occurred. Please try again.', 'voxel-toolkit'),
            ),
        ));
    }

    /**
     * AJAX handler for pin toggle
     */
    public function handle_pin_toggle() {
        try {
            // Verify nonce
            if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_timeline_pin')) {
                throw new \Exception(__('Security check failed.', 'voxel-toolkit'));
            }

            // Check if user is logged in
            if (!is_user_logged_in()) {
                throw new \Exception(__('You must be logged in to pin posts.', 'voxel-toolkit'));
            }

            // Get parameters
            $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
            $status_id = isset($_POST['status_id']) ? intval($_POST['status_id']) : 0;

            if (!$post_id || !$status_id) {
                throw new \Exception(__('Invalid request.', 'voxel-toolkit'));
            }

            // Check permission
            if (!$this->can_user_pin($post_id)) {
                throw new \Exception(__('You do not have permission to pin posts on this timeline.', 'voxel-toolkit'));
            }

            // Get current pinned status
            $current_pinned = $this->get_pinned_post($post_id);

            // Toggle pin status
            if ($current_pinned == $status_id) {
                // Unpin
                delete_post_meta($post_id, '_vt_pinned_timeline_post');
                $is_pinned = false;
                $new_pinned_id = null;
            } else {
                // Pin (this automatically unpins any previous)
                update_post_meta($post_id, '_vt_pinned_timeline_post', $status_id);
                $is_pinned = true;
                $new_pinned_id = $status_id;
            }

            return wp_send_json(array(
                'success' => true,
                'is_pinned' => $is_pinned,
                'pinned_status_id' => $new_pinned_id,
                'previous_pinned_id' => $current_pinned,
            ));

        } catch (\Exception $e) {
            return wp_send_json(array(
                'success' => false,
                'message' => $e->getMessage(),
            ));
        }
    }

    /**
     * Check if current user can pin posts on a given post's timeline
     *
     * @param int $post_id The parent post ID
     * @return bool
     */
    public function can_user_pin($post_id) {
        if (!$post_id || !is_user_logged_in()) {
            return false;
        }

        $post = get_post($post_id);
        if (!$post) {
            return false;
        }

        $current_user_id = get_current_user_id();

        // Check if current user is the post author
        return intval($post->post_author) === $current_user_id;
    }

    /**
     * Get the pinned timeline post for a given post
     *
     * @param int $post_id The parent post ID
     * @return int|null The pinned status ID or null
     */
    public function get_pinned_post($post_id) {
        if (!$post_id) {
            return null;
        }

        $pinned = get_post_meta($post_id, '_vt_pinned_timeline_post', true);
        return $pinned ? intval($pinned) : null;
    }
}
