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

        // Add pinned post example to Timeline Style Kit widget
        add_action('elementor/widget/render_content', array($this, 'add_pinned_post_example'), 10, 2);

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
     * Add style controls for pinned posts to Timeline Style Kit widget
     */
    public function add_elementor_style_controls($widget, $section_id, $args) {
        // Add to Timeline Style Kit widget (ts-timeline-kit)
        if ($widget->get_name() !== 'ts-timeline-kit') {
            return;
        }

        // Add after the spinner section (last section in the kit)
        if ($section_id !== 'ts_spinner') {
            return;
        }

        // Pinned Post Style Section
        $widget->start_controls_section(
            'vt_pinned_post_style',
            array(
                'label' => __('Pinned Post (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $widget->add_control(
            'vt_pinned_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '.vxf-post.vt-pinned' => 'border-color: {{VALUE}};',
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
            'vt_pinned_badge_align',
            array(
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-h-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-h-align-center',
                    ),
                    'right' => array(
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-h-align-right',
                    ),
                    'stretch' => array(
                        'title' => __('Full Width', 'voxel-toolkit'),
                        'icon' => 'eicon-h-align-stretch',
                    ),
                ),
                'default' => 'left',
                'selectors_dictionary' => array(
                    'left' => 'margin-right: auto;',
                    'center' => 'margin-left: auto; margin-right: auto;',
                    'right' => 'margin-left: auto;',
                    'stretch' => 'width: 100%;',
                ),
                'selectors' => array(
                    '.vt-pinned-badge' => '{{VALUE}}',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_badge_bg',
            array(
                'label' => __('Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '.vt-pinned-badge' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pinned_badge_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '.vt-pinned-badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $widget->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_pinned_badge_typo',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '.vt-pinned-badge',
            )
        );

        $widget->add_responsive_control(
            'vt_pinned_badge_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px'),
                'selectors' => array(
                    '.vt-pinned-badge' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $widget->add_responsive_control(
            'vt_pinned_badge_radius',
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
                    '.vt-pinned-badge' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $widget->add_control(
            'vt_pin_icon_heading',
            array(
                'label' => __('Pin Icon (next to actions)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $widget->add_control(
            'vt_pin_icon_color',
            array(
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '.vt-pin-icon' => 'color: {{VALUE}};',
                ),
            )
        );

        $widget->add_responsive_control(
            'vt_pin_icon_size',
            array(
                'label' => __('Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 10,
                        'max' => 30,
                    ),
                ),
                'selectors' => array(
                    '.vt-pin-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
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

    /**
     * Add pinned post example to Timeline Style Kit widget
     */
    public function add_pinned_post_example($content, $widget) {
        // Only add to Timeline Style Kit widget
        if ($widget->get_name() !== 'ts-timeline-kit') {
            return $content;
        }

        // Only in Elementor editor
        if (!\Elementor\Plugin::$instance->editor->is_edit_mode() && !\Elementor\Plugin::$instance->preview->is_preview_mode()) {
            return $content;
        }

        // Pin icon SVG
        $pin_icon = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="17" x2="12" y2="22"/><path d="M5 17h14v-1.76a2 2 0 0 0-1.11-1.79l-1.78-.9A2 2 0 0 1 15 10.76V6h1a2 2 0 0 0 0-4H8a2 2 0 0 0 0 4h1v4.76a2 2 0 0 1-1.11 1.79l-1.78.9A2 2 0 0 0 5 15.24Z"/></svg>';

        // Create pinned post example HTML - standalone section
        $pinned_html = '
        <!-- Pinned Post Example (VT) -->
        <div class="vxfeed demofeed vt-pinned-example" style="padding-top: 0; margin-bottom: 20px;">
        <div class="vxf-post vt-pinned">
            <div class="vt-pinned-badge">' . $pin_icon . '<span>' . esc_html__('Pinned', 'voxel-toolkit') . '</span></div>
            <div class="vxf-head flexify">
                <a href="#" class="vxf-avatar flexify"><img src="' . esc_url(get_template_directory_uri()) . '/assets/images/bg.jpg"></a>
                <div class="vxf-user flexify"><a href="#">Albion <div class="vxf-icon vxf-verified"><svg viewBox="0 0 22 22" xmlns="http://www.w3.org/2000/svg"><path d="M20.396 11c-.018-.646-.215-1.275-.57-1.816-.354-.54-.852-.972-1.438-1.246.223-.607.27-1.264.14-1.897-.131-.634-.437-1.218-.882-1.687-.47-.445-1.053-.75-1.687-.882-.633-.13-1.29-.083-1.897.14-.273-.587-.704-1.086-1.245-1.44S11.647 1.62 11 1.604c-.646.017-1.273.213-1.813.568s-.969.854-1.24 1.44c-.608-.223-1.267-.272-1.902-.14-.635.13-1.22.436-1.69.882-.445.47-.749 1.055-.878 1.688-.13.633-.08 1.29.144 1.896-.587.274-1.087.705-1.443 1.245-.356.54-.555 1.17-.574 1.817.02.647.218 1.276.574 1.817.356.54.856.972 1.443 1.245-.224.606-.274 1.263-.144 1.896.13.634.433 1.218.877 1.688.47.443 1.054.747 1.687.878.633.132 1.29.084 1.897-.136.274.586.705 1.084 1.246 1.439.54.354 1.17.551 1.816.569.647-.016 1.276-.213 1.817-.567s.972-.854 1.245-1.44c.604.239 1.266.296 1.903.164.636-.132 1.22-.447 1.68-.907.46-.46.776-1.044.908-1.681s.075-1.299-.165-1.903c.586-.274 1.084-.705 1.439-1.246.354-.54.551-1.17.569-1.816zM9.662 14.85l-3.429-3.428 1.293-1.302 2.072 2.072 4.4-4.794 1.347 1.246z"></path></svg></div></a><span><a href="#">@admin</a><a href="#">2h</a></span></div>
                <span class="vt-pin-icon">' . $pin_icon . '</span>
                <a href="#" class="vxf-icon vxf-more">
                    <svg viewBox="0 0 25 24" xmlns="http://www.w3.org/2000/svg" transform="rotate(0 0 0)">
                        <path d="M6.3125 13.7558C5.346 13.7559 4.5625 12.9723 4.5625 12.0059V11.9959C4.5625 11.0294 5.346 10.2458 6.3125 10.2458C7.279 10.2458 8.0625 11.0294 8.0625 11.9958V12.0058C8.0625 12.9723 7.279 13.7558 6.3125 13.7558Z"></path>
                        <path d="M18.3125 13.7558C17.346 13.7558 16.5625 12.9723 16.5625 12.0058V11.9958C16.5625 11.0294 17.346 10.2458 18.3125 10.2458C19.279 10.2458 20.0625 11.0294 20.0625 11.9958V12.0058C20.0625 12.9723 19.279 13.7558 18.3125 13.7558Z"></path>
                        <path d="M10.5625 12.0058C10.5625 12.9723 11.346 13.7558 12.3125 13.7558C13.279 13.7558 14.0625 12.9723 14.0625 12.0058V11.9958C14.0625 11.0294 13.279 10.2458 12.3125 10.2458C11.346 10.2458 10.5625 11.0294 10.5625 11.9958V12.0058Z"></path>
                    </svg>
                </a>
            </div>
            <div class="vxf-body">
                <div class="vxf-body-text">' . esc_html__('This is an example of a pinned post! It will always appear at the top of the timeline.', 'voxel-toolkit') . '</div>
            </div>
            <div class="vxf-footer flexify">
                <div class="vxf-actions flexify">
                    <a href="#" class="vxf-icon">
                        <svg width="80" height="80" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" transform="rotate(0 0 0)">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M11.8227 4.77124L12 4.94862L12.1773 4.77135C14.4244 2.52427 18.0676 2.52427 20.3147 4.77134C22.5618 7.01842 22.5618 10.6616 20.3147 12.9087L13.591 19.6324C12.7123 20.5111 11.2877 20.5111 10.409 19.6324L3.6853 12.9086C1.43823 10.6615 1.43823 7.01831 3.6853 4.77124C5.93237 2.52417 9.5756 2.52417 11.8227 4.77124ZM10.762 5.8319C9.10073 4.17062 6.40725 4.17062 4.74596 5.8319C3.08468 7.49319 3.08468 10.1867 4.74596 11.848L11.4697 18.5718C11.7625 18.8647 12.2374 18.8647 12.5303 18.5718L19.254 11.8481C20.9153 10.1868 20.9153 7.49329 19.254 5.83201C17.5927 4.17072 14.8993 4.17072 13.238 5.83201L12.5304 6.53961C12.3897 6.68026 12.199 6.75928 12 6.75928C11.8011 6.75928 11.6104 6.68026 11.4697 6.53961L10.762 5.8319Z"></path>
                        </svg>
                        <div class="ray-holder"><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div></div>
                    </a>
                    <a href="#" class="vxf-icon">
                        <svg viewBox="0 0 25 24" xmlns="http://www.w3.org/2000/svg" transform="rotate(0 0 0)">
                            <path d="M18.5324 16.7804C18.2395 16.4875 18.2394 16.0126 18.5323 15.7197C18.8252 15.4268 19.3001 15.4268 19.593 15.7196L22.0931 18.2195C22.2338 18.3601 22.3128 18.5509 22.3128 18.7498C22.3128 18.9487 22.2338 19.1395 22.0932 19.2802L19.593 21.7803C19.3001 22.0732 18.8252 22.0732 18.5323 21.7803C18.2394 21.4874 18.2394 21.0126 18.5323 20.7197L19.752 19.5H5.06267C3.82003 19.5 2.81267 18.4926 2.81267 17.25V12C2.81267 11.5858 3.14846 11.25 3.56267 11.25C3.97688 11.25 4.31267 11.5858 4.31267 12V17.25C4.31267 17.6642 4.64846 18 5.06267 18H19.7522L18.5324 16.7804Z"></path>
                            <path d="M21.0627 12.75C20.6485 12.75 20.3127 12.4142 20.3127 12V6.75C20.3127 6.33579 19.9769 6 19.5627 6H4.87316L6.09296 7.21963C6.38588 7.51251 6.38591 7.98738 6.09304 8.28029C5.80016 8.57321 5.32529 8.57324 5.03238 8.28037L2.53221 5.78054C2.39154 5.63989 2.31251 5.44912 2.3125 5.2502C2.31249 5.05127 2.39151 4.8605 2.53217 4.71984L5.03234 2.21967C5.32523 1.92678 5.80011 1.92678 6.093 2.21967C6.38589 2.51256 6.38589 2.98744 6.093 3.28033L4.87333 4.5H19.5627C20.8053 4.5 21.8127 5.50736 21.8127 6.75V12C21.8127 12.4142 21.4769 12.75 21.0627 12.75Z"></path>
                        </svg>
                        <div class="ray-holder"><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div></div>
                    </a>
                    <a href="#" class="vxf-icon">
                        <svg viewBox="0 0 25 24" xmlns="http://www.w3.org/2000/svg" transform="rotate(0 0 0)">
                            <path fill-rule="evenodd" clip-rule="evenodd" d="M12.25 3.5C7.55558 3.5 3.75 7.30558 3.75 12C3.75 13.4696 4.11813 14.8834 4.79638 16.1251C4.87612 16.271 4.90575 16.4389 4.88093 16.6033L4.35901 20.0538L7.80949 19.5319C7.97391 19.5071 8.14181 19.5367 8.28774 19.6164C9.52946 20.2947 10.9432 20.6628 12.4128 20.6628C17.1072 20.6628 20.9128 16.8572 20.9128 12.1628C20.9128 7.46839 17.0944 3.66283 12.4 3.5H12.25ZM2.25 12C2.25 6.47715 6.72715 2 12.25 2L12.4315 2.00011C17.9343 2.0854 22.4128 6.64683 22.4128 12.1628C22.4128 17.6856 17.9356 22.1628 12.4128 22.1628C10.7351 22.1628 9.11723 21.7536 7.68174 20.9827L3.27127 21.6498C2.97431 21.6947 2.67387 21.5935 2.46548 21.3791C2.25709 21.1648 2.16453 20.8617 2.21816 20.566L2.93001 16.7311C2.1092 15.2889 1.6628 13.6577 1.66281 12.0004L2.25 12Z"></path>
                        </svg>
                        <div class="ray-holder"><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div><div class="ray"></div></div>
                    </a>
                </div>
                <div class="vxf-buttons flexify"></div>
            </div>
        </div>
        </div>
        <!-- End Pinned Post Example (VT) -->';

        // Inject at the very beginning of the content
        $content = $pinned_html . $content;

        return $content;
    }
}
