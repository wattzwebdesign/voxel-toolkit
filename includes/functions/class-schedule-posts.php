<?php
/**
 * Schedule Posts
 *
 * Adds scheduled post functionality to Voxel's Create Post widget.
 * Users can select a future date/time to schedule post publication.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Schedule_Posts {

    private static $instance = null;

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
     * Widgets with schedule posts enabled
     */
    private $schedule_widgets = array();

    /**
     * Constructor
     */
    private function __construct() {
        // Add Elementor controls to Create Post widget
        add_action('elementor/element/ts-create-post/ts_sf_post_types/after_section_end', array($this, 'add_widget_controls'), 10, 2);

        // Capture widget settings when rendered
        add_action('elementor/frontend/widget/before_render', array($this, 'capture_widget_settings'), 10, 1);

        // Inject frontend JavaScript
        add_action('wp_footer', array($this, 'render_frontend_script'), 20);

        // Enqueue CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // Also enqueue CSS in Elementor editor preview
        add_action('elementor/preview/enqueue_styles', array($this, 'enqueue_styles'));

        // Hook into post creation/update to handle scheduling
        add_filter('wp_insert_post_data', array($this, 'handle_scheduled_post'), 10, 2);

        // Also hook into Voxel's post updated action as a fallback
        add_action('voxel/frontend/post_updated', array($this, 'handle_voxel_scheduled_post'), 10, 1);
    }

    /**
     * Capture widget settings when Create Post widget is rendered
     */
    public function capture_widget_settings($widget) {
        if ($widget->get_name() !== 'ts-create-post') {
            return;
        }

        $settings = $widget->get_settings_for_display();

        if (isset($settings['vt_enable_schedule_posts']) && $settings['vt_enable_schedule_posts'] === 'yes') {
            $this->schedule_widgets[$widget->get_id()] = array(
                'enabled' => true,
                'label' => isset($settings['vt_schedule_label']) ? $settings['vt_schedule_label'] : __('Schedule Posting', 'voxel-toolkit'),
                'date_placeholder' => isset($settings['vt_schedule_date_placeholder']) ? $settings['vt_schedule_date_placeholder'] : __('Select date', 'voxel-toolkit'),
                'time_placeholder' => isset($settings['vt_schedule_time_placeholder']) ? $settings['vt_schedule_time_placeholder'] : '00:00',
                'button_text' => isset($settings['vt_schedule_button_text']) ? $settings['vt_schedule_button_text'] : __('Schedule', 'voxel-toolkit'),
                'success_message' => isset($settings['vt_schedule_success_message']) ? $settings['vt_schedule_success_message'] : __('Your post has been scheduled.', 'voxel-toolkit'),
            );
        }
    }

    /**
     * Add controls to Create Post widget
     */
    public function add_widget_controls($element, $args) {
        $element->start_controls_section(
            'vt_schedule_posts_section',
            array(
                'label' => __('Schedule Posts (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $element->add_control(
            'vt_enable_schedule_posts',
            array(
                'label' => __('Enable Schedule Posts', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Allow users to schedule posts for future publication. Only works when posts are set to publish (not pending).', 'voxel-toolkit'),
            )
        );

        $element->add_control(
            'vt_schedule_label',
            array(
                'label' => __('Field Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Schedule Posting', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_date_placeholder',
            array(
                'label' => __('Date Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Select date', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_time_placeholder',
            array(
                'label' => __('Time Placeholder', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '00:00',
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_button_text',
            array(
                'label' => __('Schedule Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Schedule', 'voxel-toolkit'),
                'description' => __('Text to show on the submit button when a schedule date is selected.', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_success_message',
            array(
                'label' => __('Scheduled Success Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Your post has been scheduled.', 'voxel-toolkit'),
                'description' => __('Message shown after successfully scheduling a post.', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        $element->end_controls_section();

        // Style Section
        $element->start_controls_section(
            'vt_schedule_posts_style_section',
            array(
                'label' => __('Schedule Posts Style (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => array(
                    'vt_enable_schedule_posts' => 'yes',
                ),
            )
        );

        // Label Typography
        $element->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'vt_schedule_label_typography',
                'label' => __('Label Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-schedule-field label',
            )
        );

        $element->add_control(
            'vt_schedule_label_color',
            array(
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-field label' => 'color: {{VALUE}}',
                ),
            )
        );

        // Input Styling
        $element->add_control(
            'vt_schedule_input_heading',
            array(
                'label' => __('Input Fields', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $element->add_control(
            'vt_schedule_input_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-field .ts-filter' => 'background-color: {{VALUE}}',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_input_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-field .ts-filter' => 'border-color: {{VALUE}}',
                ),
            )
        );

        $element->add_responsive_control(
            'vt_schedule_input_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-field .ts-filter' => 'border-radius: {{SIZE}}{{UNIT}}',
                ),
            )
        );

        // Calendar Popup
        $element->add_control(
            'vt_schedule_calendar_heading',
            array(
                'label' => __('Calendar Popup', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $element->add_control(
            'vt_schedule_calendar_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-calendar' => 'background-color: {{VALUE}}',
                ),
            )
        );

        $element->add_control(
            'vt_schedule_calendar_accent',
            array(
                'label' => __('Accent Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-schedule-calendar .is-selected .pika-button' => 'background-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-schedule-calendar .pika-button:hover' => 'border-color: {{VALUE}}',
                    '{{WRAPPER}} .vt-schedule-popup .ts-btn-2' => 'background-color: {{VALUE}}',
                ),
            )
        );

        $element->end_controls_section();
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        if (empty($this->schedule_widgets)) {
            return;
        }

        wp_enqueue_style(
            'vt-schedule-posts',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/schedule-posts.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Render frontend script
     */
    public function render_frontend_script() {
        if (empty($this->schedule_widgets)) {
            return;
        }

        // Schedule posts CSS
        $schedule_css = VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/schedule-posts.css?ver=' . VOXEL_TOOLKIT_VERSION;

        // Output CSS
        ?>
        <link rel="stylesheet" href="<?php echo esc_url($schedule_css); ?>" media="all">
        <?php

        $widgets_data = array();
        foreach ($this->schedule_widgets as $widget_id => $settings) {
            $widgets_data[$widget_id] = $settings;
        }

        // Get current post's scheduled date if editing
        $scheduled_date = '';
        $scheduled_time = '';

        if (isset($_GET['post_id'])) {
            $post_id = absint($_GET['post_id']);
            $post = get_post($post_id);
            if ($post && $post->post_status === 'future') {
                $scheduled_date = get_the_date('Y-m-d', $post);
                $scheduled_time = get_the_date('H:i', $post);
            }
        }

        ?>
        <script>
        (function() {
            'use strict';

            const widgetsData = <?php echo json_encode($widgets_data); ?>;
            const existingDate = '<?php echo esc_js($scheduled_date); ?>';
            const existingTime = '<?php echo esc_js($scheduled_time); ?>';

            // Get today's date in YYYY-MM-DD format for min attribute
            const today = new Date();
            const minDate = today.toISOString().split('T')[0];

            // Get the first widget's success message for scheduled posts
            let scheduledSuccessMessage = '<?php _e('Your post has been scheduled.', 'voxel-toolkit'); ?>';
            for (const wid in widgetsData) {
                if (widgetsData[wid].success_message) {
                    scheduledSuccessMessage = widgetsData[wid].success_message;
                    break;
                }
            }

            // Helper to get schedule values from DOM
            function getScheduleValues() {
                const visibleDateInput = document.querySelector('.vt-schedule-date');
                const visibleTimeInput = document.querySelector('.vt-schedule-time');
                const hiddenDateInput = document.querySelector('.vt-schedule-date-input');
                const hiddenTimeInput = document.querySelector('.vt-schedule-time-input');

                console.log('VT Schedule: visibleDateInput found:', !!visibleDateInput);
                console.log('VT Schedule: visibleDateInput value:', visibleDateInput ? visibleDateInput.value : 'N/A');
                console.log('VT Schedule: hiddenDateInput found:', !!hiddenDateInput);
                console.log('VT Schedule: hiddenDateInput value:', hiddenDateInput ? hiddenDateInput.value : 'N/A');

                const scheduleDate = (visibleDateInput && visibleDateInput.value) || (hiddenDateInput && hiddenDateInput.value) || '';
                const scheduleTime = (visibleTimeInput && visibleTimeInput.value) || (hiddenTimeInput && hiddenTimeInput.value) || '00:00';

                console.log('VT Schedule: Final values - date:', scheduleDate, 'time:', scheduleTime);
                return { scheduleDate, scheduleTime };
            }

            // Intercept XHR to inject schedule data - do this ONCE globally
            if (!window._vtScheduleXHRPatched) {
                window._vtScheduleXHRPatched = true;
                const originalXHRSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.send = function(data) {
                    let modifiedData = data;
                    if (data) {
                        const { scheduleDate, scheduleTime } = getScheduleValues();

                        if (scheduleDate) {
                            console.log('VT Schedule: XHR send intercepted');
                            console.log('VT Schedule: Date:', scheduleDate, 'Time:', scheduleTime);

                            window._vtSchedulePending = true;
                            window._vtScheduleSuccessMessage = scheduledSuccessMessage;

                            if (data instanceof FormData) {
                                console.log('VT Schedule: Appending to FormData (XHR)');
                                data.append('vt_schedule_date', scheduleDate);
                                data.append('vt_schedule_time', scheduleTime);
                                modifiedData = data;
                            } else if (typeof data === 'string' && data.includes('action=')) {
                                console.log('VT Schedule: Appending to string data (XHR)');
                                modifiedData = data + '&vt_schedule_date=' + encodeURIComponent(scheduleDate) + '&vt_schedule_time=' + encodeURIComponent(scheduleTime);
                                console.log('VT Schedule: Modified data:', modifiedData);
                            }
                        }
                    }
                    return originalXHRSend.call(this, modifiedData);
                };

                // Also intercept fetch API
                const originalFetch = window.fetch;
                window.fetch = function(url, options) {
                    const { scheduleDate, scheduleTime } = getScheduleValues();

                    if (scheduleDate && options && options.body) {
                        console.log('VT Schedule: Fetch intercepted');
                        console.log('VT Schedule: Date:', scheduleDate, 'Time:', scheduleTime);

                        window._vtSchedulePending = true;
                        window._vtScheduleSuccessMessage = scheduledSuccessMessage;

                        if (options.body instanceof FormData) {
                            console.log('VT Schedule: Appending to FormData (fetch)');
                            options.body.append('vt_schedule_date', scheduleDate);
                            options.body.append('vt_schedule_time', scheduleTime);
                        } else if (typeof options.body === 'string') {
                            console.log('VT Schedule: Appending to string body (fetch)');
                            if (options.body.includes('=')) {
                                options.body += '&vt_schedule_date=' + encodeURIComponent(scheduleDate);
                                options.body += '&vt_schedule_time=' + encodeURIComponent(scheduleTime);
                            }
                        }
                    }
                    return originalFetch.call(this, url, options);
                };
            }

            // Function to replace confirmation message for scheduled posts
            function replaceScheduledMessage() {
                console.log('VT Schedule: replaceScheduledMessage called, pending:', window._vtSchedulePending);

                if (!window._vtSchedulePending || !window._vtScheduleSuccessMessage) {
                    return;
                }

                // Look for "Your post has been published" text - be flexible with matching
                const searchText = 'your post has been published';

                // Target heading elements in confirmation pages (Voxel uses h4 for success messages)
                const headings = document.querySelectorAll('.ts-edit-success h4, .ts-create-post h4, h4, h2, h3');
                console.log('VT Schedule: Found', headings.length, 'heading elements');

                headings.forEach(function(heading) {
                    const text = heading.textContent.trim().toLowerCase();
                    if (text.includes(searchText) && !heading.hasAttribute('data-vt-replaced')) {
                        console.log('VT Schedule: Replacing confirmation message in', heading.tagName);
                        heading.textContent = window._vtScheduleSuccessMessage;
                        heading.setAttribute('data-vt-replaced', 'true');
                        // Clear the flag and remove hiding class
                        window._vtSchedulePending = false;
                        document.body.classList.remove('vt-schedule-pending');
                    }
                });
            }

            function initScheduleField(widgetId, settings) {
                const widget = document.querySelector('[data-id="' + widgetId + '"]');
                if (!widget) {
                    console.log('VT Schedule: Widget not found');
                    return;
                }

                const form = widget.querySelector('.ts-form.create-post-form, .ts-create-post, .ts-form');
                if (!form) {
                    console.log('VT Schedule: Form not found');
                    return;
                }

                // Check if we're on a confirmation/success page by looking for specific elements
                const hasSuccessIcon = form.querySelector('.ts-checkmark-circle, .success-icon, svg.checkmark');
                const formText = form.textContent || '';
                const isConfirmationPage = hasSuccessIcon ||
                    (formText.includes('has been published') && formText.includes('View'));

                if (isConfirmationPage) {
                    console.log('VT Schedule: On confirmation page, skipping');
                    const existingField = form.querySelector('.vt-schedule-field');
                    if (existingField) existingField.remove();
                    return;
                }

                // Find the submit button - try multiple selectors
                let submitBtn = form.querySelector('.ts-form-group.submit-form .ts-btn');
                if (!submitBtn) {
                    submitBtn = form.querySelector('.submit-form .ts-btn');
                }
                if (!submitBtn) {
                    submitBtn = form.querySelector('.ts-btn.ts-btn-2.ts-btn-large');
                }

                if (!submitBtn) {
                    console.log('VT Schedule: Submit button not found');
                    return;
                }

                console.log('VT Schedule: Found submit button:', submitBtn.textContent);

                // Make sure it's not a View or Back button (confirmation page buttons)
                const btnText = submitBtn.textContent.toLowerCase().trim();
                if (btnText === 'view' || btnText.includes('back to')) {
                    console.log('VT Schedule: Button is View/Back, skipping');
                    return;
                }

                // Check if submit button is visible (on current step)
                let submitWrapper = submitBtn.closest('.ts-form-group');
                if (!submitWrapper) {
                    // No .ts-form-group wrapper - use button's parent or the button itself
                    submitWrapper = submitBtn.parentElement;
                }

                // Check visibility
                const rect = submitBtn.getBoundingClientRect();
                if (rect.width === 0 || rect.height === 0 || submitBtn.offsetParent === null) {
                    return;
                }

                // Check if already injected
                if (form.querySelector('.vt-schedule-field')) {
                    return;
                }

                // Create schedule field HTML using native date/time inputs
                const scheduleField = document.createElement('div');
                scheduleField.className = 'ts-form-group vt-schedule-field';
                scheduleField.innerHTML = `
                    <label>${settings.label}</label>
                    <div class="form-field-grid medium vt-schedule-inputs">
                        <div class="ts-form-group vx-2-3">
                            <input type="date" class="ts-filter vt-schedule-date" min="${minDate}" value="${existingDate}" placeholder="${settings.date_placeholder}">
                        </div>
                        <div class="ts-form-group vx-1-3">
                            <input type="time" class="ts-filter vt-schedule-time" value="${existingTime || '00:00'}">
                        </div>
                    </div>
                    <input type="hidden" name="vt_schedule_date" class="vt-schedule-date-input" value="${existingDate}">
                    <input type="hidden" name="vt_schedule_time" class="vt-schedule-time-input" value="${existingTime}">
                `;

                // Insert before submit button
                submitWrapper.parentNode.insertBefore(scheduleField, submitWrapper);

                // Get input elements
                const dateInput = scheduleField.querySelector('.vt-schedule-date');
                const timeInput = scheduleField.querySelector('.vt-schedule-time');
                const hiddenDateInput = scheduleField.querySelector('.vt-schedule-date-input');
                const hiddenTimeInput = scheduleField.querySelector('.vt-schedule-time-input');

                // Store original button text
                const originalBtnText = submitBtn.textContent.trim();
                const scheduleBtnText = settings.button_text || 'Schedule';

                // Update button text based on date selection
                function updateButtonText() {
                    if (dateInput.value) {
                        submitBtn.textContent = scheduleBtnText;
                    } else {
                        submitBtn.textContent = originalBtnText;
                    }
                }

                // Sync date input to hidden field (on both input and change for reliability)
                dateInput.addEventListener('input', function() {
                    hiddenDateInput.value = this.value;
                    updateButtonText();
                });
                dateInput.addEventListener('change', function() {
                    hiddenDateInput.value = this.value;
                    updateButtonText();
                });

                // Sync time input to hidden field
                timeInput.addEventListener('input', function() {
                    hiddenTimeInput.value = this.value;
                });
                timeInput.addEventListener('change', function() {
                    hiddenTimeInput.value = this.value;
                });

                // Initialize hidden fields if values exist
                if (existingDate) {
                    hiddenDateInput.value = existingDate;
                    updateButtonText();
                }
                if (existingTime) {
                    hiddenTimeInput.value = existingTime;
                }

            }

            // Check if submit button is visible on current step
            function isSubmitVisible(widget) {
                const form = widget.querySelector('.ts-form.create-post-form, .ts-create-post, .ts-form');
                if (!form) return false;

                // Check if we're on confirmation page
                const formText = form.textContent || '';
                const hasSuccessIcon = form.querySelector('.ts-checkmark-circle, .success-icon, svg.checkmark');
                if (hasSuccessIcon || (formText.includes('has been published') && formText.includes('View'))) {
                    return false;
                }

                // Try to find the submit/publish button
                let submitBtn = form.querySelector('.ts-form-group.submit-form .ts-btn');
                if (!submitBtn) {
                    submitBtn = form.querySelector('.submit-form .ts-btn');
                }
                if (!submitBtn) {
                    submitBtn = form.querySelector('.ts-btn.ts-btn-2.ts-btn-large');
                }
                if (!submitBtn) return false;

                // Make sure it's not a View or Back button
                const btnText = submitBtn.textContent.toLowerCase().trim();
                if (btnText === 'view' || btnText.includes('back to')) {
                    return false;
                }

                // Check if visible (not hidden by CSS or in a hidden step)
                const rect = submitBtn.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0 && submitBtn.offsetParent !== null;
            }

            // Handle step changes - show/hide schedule field
            function handleStepChange(widgetId, settings) {
                const widget = document.querySelector('[data-id="' + widgetId + '"]');
                if (!widget) return;

                const form = widget.querySelector('.ts-form.create-post-form, .ts-create-post, .ts-form');
                const existingField = widget.querySelector('.vt-schedule-field');

                // Check if we're on confirmation page
                const formText = form ? (form.textContent || '') : '';
                const hasSuccessIcon = form ? form.querySelector('.ts-checkmark-circle, .success-icon, svg.checkmark') : false;
                const isConfirmation = hasSuccessIcon || (formText.includes('has been published') && formText.includes('View'));

                if (isConfirmation && existingField) {
                    // On confirmation page - remove scheduler
                    existingField.remove();
                    return;
                }

                const submitVisible = isSubmitVisible(widget);

                if (submitVisible && !existingField) {
                    // Submit is visible but field not added - add it
                    initScheduleField(widgetId, settings);
                } else if (!submitVisible && existingField) {
                    // Submit not visible but field exists - remove it
                    existingField.remove();
                }
            }

            // Wait for DOM and Vue to be ready
            function init() {
                // Wait a bit for Voxel's Vue components to render
                setTimeout(function() {
                    for (const widgetId in widgetsData) {
                        handleStepChange(widgetId, widgetsData[widgetId]);
                    }
                    // Also check for message replacement
                    replaceScheduledMessage();
                }, 500);

                // Also observe for dynamic content and step changes
                const observer = new MutationObserver(function(mutations) {
                    // Debounce to avoid too many calls
                    clearTimeout(window.vtScheduleDebounce);
                    window.vtScheduleDebounce = setTimeout(function() {
                        for (const widgetId in widgetsData) {
                            handleStepChange(widgetId, widgetsData[widgetId]);
                        }
                        // Check for confirmation message replacement
                        replaceScheduledMessage();
                    }, 100);
                });

                observer.observe(document.body, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['style', 'class']
                });
            }

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', init);
            } else {
                init();
            }
        })();
        </script>
        <?php
    }

    /**
     * Handle scheduled post on save
     */
    public function handle_scheduled_post($data, $postarr) {
        // Check if schedule data was submitted
        if (empty($_POST['vt_schedule_date'])) {
            return $data;
        }

        $schedule_date = sanitize_text_field($_POST['vt_schedule_date']);
        $schedule_time = isset($_POST['vt_schedule_time']) ? sanitize_text_field($_POST['vt_schedule_time']) : '00:00';

        // Validate date format (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
            return $data;
        }

        // Validate time format (HH:MM or HH:MM:SS)
        if (!preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $schedule_time)) {
            $schedule_time = '00:00';
        }

        // Combine date and time
        $schedule_datetime = $schedule_date . ' ' . $schedule_time . ':00';
        $schedule_timestamp = strtotime($schedule_datetime);

        // Only schedule if:
        // 1. Post would be published or pending
        // 2. Schedule time is in the future
        if (in_array($data['post_status'], ['publish', 'pending'], true) && $schedule_timestamp > time()) {
            $data['post_status'] = 'future';
            $data['post_date'] = $schedule_datetime;
            $data['post_date_gmt'] = get_gmt_from_date($schedule_datetime);
        }

        return $data;
    }

    /**
     * Handle scheduled post via Voxel's hook (runs after post is saved)
     */
    public function handle_voxel_scheduled_post($args) {
        // Check if schedule data was submitted
        $schedule_date = isset($_POST['vt_schedule_date']) ? sanitize_text_field($_POST['vt_schedule_date']) : '';
        $schedule_time = isset($_POST['vt_schedule_time']) ? sanitize_text_field($_POST['vt_schedule_time']) : '00:00';

        // Also check $_REQUEST as fallback
        if (empty($schedule_date) && isset($_REQUEST['vt_schedule_date'])) {
            $schedule_date = sanitize_text_field($_REQUEST['vt_schedule_date']);
            $schedule_time = isset($_REQUEST['vt_schedule_time']) ? sanitize_text_field($_REQUEST['vt_schedule_time']) : '00:00';
        }

        if (empty($schedule_date)) {
            return;
        }

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
            return;
        }

        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $schedule_time)) {
            $schedule_time = '00:00';
        }

        // Get the post
        $post = isset($args['post']) ? $args['post'] : null;
        if (!$post) {
            return;
        }

        $post_id = $post->get_id();
        $current_status = $args['status'] ?? '';

        // Combine date and time
        $schedule_datetime = $schedule_date . ' ' . $schedule_time . ':00';

        // Use WordPress timezone for proper handling
        $wp_timezone = wp_timezone();
        $schedule_dt = new \DateTime($schedule_datetime, $wp_timezone);
        $current_dt = new \DateTime('now', $wp_timezone);

        $schedule_timestamp = $schedule_dt->getTimestamp();
        $current_timestamp = $current_dt->getTimestamp();

        // Only schedule if:
        // 1. Post is currently published or pending
        // 2. Schedule time is in the future
        if (in_array($current_status, ['publish', 'pending'], true) && $schedule_timestamp > $current_timestamp) {
            // Update post to scheduled status
            wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'future',
                'post_date' => $schedule_datetime,
                'post_date_gmt' => get_gmt_from_date($schedule_datetime),
                'edit_date' => true,
            ));
        }
    }
}
