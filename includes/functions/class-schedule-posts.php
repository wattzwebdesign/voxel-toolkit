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

            // Calendar icon SVG
            const calendarIcon = '<svg fill="currentColor" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" width="20" height="20"><path d="M1 4c0-1.1.9-2 2-2h14a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V4zm2 2v12h14V6H3zm2-6h2v2H5V0zm8 0h2v2h-2V0zM5 9h2v2H5V9zm0 4h2v2H5v-2zm4-4h2v2H9V9zm0 4h2v2H9v-2zm4-4h2v2h-2V9zm0 4h2v2h-2v-2z"/></svg>';

            // Intercept XHR to inject schedule data - do this ONCE globally
            if (!window._vtScheduleXHRPatched) {
                window._vtScheduleXHRPatched = true;
                const originalXHRSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.send = function(data) {
                    // Check if this is an admin-ajax request with data
                    if (data) {
                        // Find schedule inputs in DOM
                        const dateInput = document.querySelector('.vt-schedule-date-input');
                        const timeInput = document.querySelector('.vt-schedule-time-input');

                        if (dateInput && dateInput.value) {
                            const scheduleDate = dateInput.value;
                            const scheduleTime = timeInput ? timeInput.value : '00:00';

                            console.log('VT Schedule: XHR send intercepted');
                            console.log('VT Schedule: Date:', scheduleDate, 'Time:', scheduleTime);

                            if (data instanceof FormData) {
                                console.log('VT Schedule: Appending to FormData');
                                data.append('vt_schedule_date', scheduleDate);
                                data.append('vt_schedule_time', scheduleTime);
                            } else if (typeof data === 'string' && data.includes('action=')) {
                                console.log('VT Schedule: Appending to string data');
                                data += '&vt_schedule_date=' + encodeURIComponent(scheduleDate);
                                data += '&vt_schedule_time=' + encodeURIComponent(scheduleTime);
                            }
                        }
                    }
                    return originalXHRSend.call(this, data);
                };
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

                // Create schedule field HTML using Voxel's popup structure
                const scheduleField = document.createElement('div');
                scheduleField.className = 'ts-form-group vt-schedule-field';
                scheduleField.innerHTML = `
                    <label style="margin-top: 25px;">${settings.label}</label>
                    <div class="form-field-grid medium">
                        <div class="ts-form-group vx-2-3" style="position: relative;">
                            <div class="ts-filter ts-popup-target vt-schedule-date-trigger" data-filled="false">
                                ${calendarIcon}
                                <div class="ts-filter-text">${settings.date_placeholder}</div>
                            </div>
                            <div class="ts-field-popup-container vt-schedule-popup-container" style="display: none;">
                                <div class="ts-field-popup ts-popup-fit">
                                    <div class="ts-popup-content-wrapper min-scroll">
                                        <div class="ts-booking-date ts-booking-date-single vt-schedule-calendar"></div>
                                    </div>
                                    <div class="ts-popup-controller">
                                        <ul class="flexify simplify-ul">
                                            <li class="flexify">
                                                <a href="#" class="ts-btn ts-btn-1 vt-schedule-clear"><?php _e('Clear', 'voxel-toolkit'); ?></a>
                                            </li>
                                            <li class="flexify">
                                                <a href="#" class="ts-btn ts-btn-2 vt-schedule-save"><?php _e('Save', 'voxel-toolkit'); ?></a>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="ts-form-group vx-1-3">
                            <input type="time" class="ts-filter vt-schedule-time" value="${existingTime || settings.time_placeholder}">
                        </div>
                    </div>
                    <input type="hidden" name="vt_schedule_date" class="vt-schedule-date-input" value="${existingDate}">
                    <input type="hidden" name="vt_schedule_time" class="vt-schedule-time-input" value="${existingTime}">
                `;

                // Insert before submit button
                submitWrapper.parentNode.insertBefore(scheduleField, submitWrapper);

                // Initialize Pikaday
                const calendarContainer = scheduleField.querySelector('.vt-schedule-calendar');
                const dateTrigger = scheduleField.querySelector('.vt-schedule-date-trigger');
                const dateText = dateTrigger.querySelector('.ts-filter-text');
                const popup = scheduleField.querySelector('.vt-schedule-popup-container');
                const timeInput = scheduleField.querySelector('.vt-schedule-time');
                const hiddenDateInput = scheduleField.querySelector('.vt-schedule-date-input');
                const hiddenTimeInput = scheduleField.querySelector('.vt-schedule-time-input');
                const clearBtn = scheduleField.querySelector('.vt-schedule-clear');
                const saveBtn = scheduleField.querySelector('.vt-schedule-save');

                let selectedDate = existingDate ? new Date(existingDate + 'T00:00:00') : null;
                let picker = null;

                // Store original button text and get button reference for text changes
                const originalBtnText = submitBtn.textContent.trim();
                const scheduleBtnText = settings.button_text || 'Schedule';

                // Check if Pikaday is available
                if (typeof Pikaday !== 'undefined') {
                    picker = new Pikaday({
                        field: document.createElement('input'), // Dummy field
                        container: calendarContainer,
                        bound: false,
                        firstDay: 1,
                        minDate: new Date(),
                        keyboardInput: false,
                        defaultDate: selectedDate || new Date(),
                        setDefaultDate: !!selectedDate,
                        onSelect: function(date) {
                            selectedDate = date;
                        }
                    });
                }

                // Update display if existing date
                if (existingDate) {
                    dateText.textContent = formatDate(selectedDate);
                    dateTrigger.setAttribute('data-filled', 'true');
                    dateTrigger.classList.add('ts-filled');
                }

                // Toggle popup
                dateTrigger.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    popup.style.display = popup.style.display === 'none' ? 'block' : 'none';
                });

                // Stop propagation on popup clicks (calendar, buttons, etc.)
                popup.addEventListener('click', function(e) {
                    e.stopPropagation();
                });

                // Close popup when clicking outside
                document.addEventListener('click', function(e) {
                    if (!scheduleField.contains(e.target)) {
                        popup.style.display = 'none';
                    }
                });

                // Clear button
                clearBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    selectedDate = null;
                    hiddenDateInput.value = '';
                    hiddenTimeInput.value = '';
                    dateText.textContent = settings.date_placeholder;
                    dateTrigger.setAttribute('data-filled', 'false');
                    dateTrigger.classList.remove('ts-filled');
                    timeInput.value = settings.time_placeholder;
                    popup.style.display = 'none';

                    // Restore original button text
                    submitBtn.textContent = originalBtnText;
                    if (picker) {
                        picker.setDate(null);
                    }
                });

                // Save button
                saveBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    if (selectedDate) {
                        const year = selectedDate.getFullYear();
                        const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
                        const day = String(selectedDate.getDate()).padStart(2, '0');
                        hiddenDateInput.value = `${year}-${month}-${day}`;
                        hiddenTimeInput.value = timeInput.value || '00:00';
                        dateText.textContent = formatDate(selectedDate);
                        dateTrigger.setAttribute('data-filled', 'true');
                        dateTrigger.classList.add('ts-filled');

                        // Change submit button text to schedule text
                        submitBtn.textContent = scheduleBtnText;
                    }
                    popup.style.display = 'none';
                });

                // Sync time input
                timeInput.addEventListener('change', function() {
                    hiddenTimeInput.value = this.value;
                });

                function formatDate(date) {
                    if (!date) return settings.date_placeholder;
                    const options = { year: 'numeric', month: 'short', day: 'numeric' };
                    return date.toLocaleDateString(undefined, options);
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
                }, 500);

                // Also observe for dynamic content and step changes
                const observer = new MutationObserver(function(mutations) {
                    // Debounce to avoid too many calls
                    clearTimeout(window.vtScheduleDebounce);
                    window.vtScheduleDebounce = setTimeout(function() {
                        for (const widgetId in widgetsData) {
                            handleStepChange(widgetId, widgetsData[widgetId]);
                        }
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

        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $schedule_date)) {
            return $data;
        }

        // Validate time format
        if (!preg_match('/^\d{2}:\d{2}$/', $schedule_time)) {
            $schedule_time = '00:00';
        }

        // Combine date and time
        $schedule_datetime = $schedule_date . ' ' . $schedule_time . ':00';
        $schedule_timestamp = strtotime($schedule_datetime);

        // Only schedule if:
        // 1. Post would be published (not pending)
        // 2. Schedule time is in the future
        if ($data['post_status'] === 'publish' && $schedule_timestamp > time()) {
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
        // Debug logging
        error_log('VT Schedule: ========== Voxel hook triggered ==========');
        error_log('VT Schedule: Args: ' . print_r($args, true));
        error_log('VT Schedule: POST keys: ' . implode(', ', array_keys($_POST)));
        error_log('VT Schedule: vt_schedule_date in POST: ' . (isset($_POST['vt_schedule_date']) ? $_POST['vt_schedule_date'] : 'NOT SET'));
        error_log('VT Schedule: REQUEST keys: ' . implode(', ', array_keys($_REQUEST)));

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
        // 1. Post is currently published
        // 2. Schedule time is in the future
        error_log('VT Schedule: Current status: ' . $current_status);
        error_log('VT Schedule: Schedule datetime: ' . $schedule_datetime);
        error_log('VT Schedule: Schedule timestamp: ' . $schedule_timestamp . ' vs current time: ' . $current_timestamp);
        error_log('VT Schedule: WP Timezone: ' . $wp_timezone->getName());
        error_log('VT Schedule: Is future? ' . ($schedule_timestamp > $current_timestamp ? 'YES' : 'NO'));

        if ($current_status === 'publish' && $schedule_timestamp > $current_timestamp) {
            error_log('VT Schedule: Updating post ' . $post_id . ' to future status');
            // Update post to scheduled status
            $result = wp_update_post(array(
                'ID' => $post_id,
                'post_status' => 'future',
                'post_date' => $schedule_datetime,
                'post_date_gmt' => get_gmt_from_date($schedule_datetime),
                'edit_date' => true,
            ));
            error_log('VT Schedule: wp_update_post result: ' . print_r($result, true));
        } else {
            error_log('VT Schedule: NOT scheduling - status=' . $current_status . ' or time not in future');
        }
    }
}
