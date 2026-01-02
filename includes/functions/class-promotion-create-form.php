<?php
/**
 * Promotion Create Form
 *
 * Adds promotion package selection to Voxel's Create Form widget.
 * Users can optionally select a promotion package before submitting,
 * then get redirected to checkout after post creation.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Promotion_Create_Form {

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
     * Widgets with promotions enabled
     */
    private $promotion_widgets = array();

    /**
     * Constructor
     */
    private function __construct() {
        // Add Elementor controls to Create Post widget
        add_action('elementor/element/ts-create-post/ts_sf_post_types/after_section_end', array($this, 'add_widget_controls'), 10, 2);

        // Capture widget settings when rendered
        add_action('elementor/frontend/widget/before_render', array($this, 'capture_widget_settings'), 10, 1);

        // Inject frontend JavaScript/Vue component
        add_action('wp_footer', array($this, 'render_frontend_script'), 20);

        // Enqueue CSS
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));

        // AJAX handler to get promotion packages for a post type
        add_action('wp_ajax_vt_get_promotion_packages', array($this, 'ajax_get_packages'));
        add_action('wp_ajax_nopriv_vt_get_promotion_packages', array($this, 'ajax_get_packages'));
    }

    /**
     * Capture widget settings when Create Post widget is rendered
     */
    public function capture_widget_settings($widget) {
        if ($widget->get_name() !== 'ts-create-post') {
            return;
        }

        $settings = $widget->get_settings_for_display();

        if (isset($settings['vt_enable_promotions']) && $settings['vt_enable_promotions'] === 'yes') {
            $this->promotion_widgets[$widget->get_id()] = array(
                'enabled' => true,
                'title' => isset($settings['vt_promotions_title']) ? $settings['vt_promotions_title'] : __('Boost your listing', 'voxel-toolkit'),
                'description' => isset($settings['vt_promotions_description']) ? $settings['vt_promotions_description'] : __('Get more visibility with a promotion package (optional)', 'voxel-toolkit'),
                'skip_text' => isset($settings['vt_promotions_skip_text']) ? $settings['vt_promotions_skip_text'] : __('No thanks, just submit', 'voxel-toolkit'),
            );
        }
    }

    /**
     * Add "Enable Promotions" controls to Create Post widget
     */
    public function add_widget_controls($element, $args) {
        $element->start_controls_section(
            'vt_promotions_section',
            array(
                'label' => __('Promotions (VT)', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $element->add_control(
            'vt_enable_promotions',
            array(
                'label' => __('Enable Promotions', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Show promotion packages at the end of the form before submit.', 'voxel-toolkit'),
            )
        );

        $element->add_control(
            'vt_promotions_title',
            array(
                'label' => __('Section Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Boost your listing', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_promotions_description',
            array(
                'label' => __('Section Description', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Get more visibility with a promotion package (optional)', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->add_control(
            'vt_promotions_skip_text',
            array(
                'label' => __('Skip Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No thanks, just submit', 'voxel-toolkit'),
                'condition' => array(
                    'vt_enable_promotions' => 'yes',
                ),
            )
        );

        $element->end_controls_section();
    }

    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        // Only enqueue on pages that might have create-post widget
        if (!is_singular() && !is_page()) {
            return;
        }

        $css_file = VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/functions/assets/css/promotion-create-form.css';
        if (file_exists($css_file)) {
            wp_enqueue_style(
                'vt-promotion-create-form',
                VOXEL_TOOLKIT_PLUGIN_URL . 'includes/functions/assets/css/promotion-create-form.css',
                array(),
                VOXEL_TOOLKIT_VERSION
            );
        }
    }

    /**
     * Get promotion packages from Voxel settings
     */
    private function get_all_packages() {
        $paid_listings = get_option('voxel:paid_listings', '');

        // Voxel stores this as JSON string, not serialized PHP array
        if (is_string($paid_listings) && !empty($paid_listings)) {
            $paid_listings = json_decode($paid_listings, true);
        }

        if (empty($paid_listings) || !is_array($paid_listings)) {
            return array();
        }

        $settings = isset($paid_listings['settings']) ? $paid_listings['settings'] : array();
        $promotions = isset($settings['promotions']) ? $settings['promotions'] : array();
        $packages = isset($promotions['packages']) ? $promotions['packages'] : array();

        return $packages;
    }

    /**
     * Get packages filtered by post type
     */
    private function get_packages_for_post_type($post_type) {
        $all_packages = $this->get_all_packages();
        $filtered = array();

        foreach ($all_packages as $package) {
            // Check if package applies to this post type
            $package_post_types = isset($package['post_types']) ? $package['post_types'] : array();

            // If no post types specified, it applies to all
            if (empty($package_post_types) || in_array($post_type, $package_post_types)) {
                $filtered[] = array(
                    'key' => isset($package['key']) ? $package['key'] : '',
                    'label' => isset($package['ui']['label']) ? $package['ui']['label'] : __('Promotion', 'voxel-toolkit'),
                    'description' => isset($package['ui']['description']) ? $package['ui']['description'] : '',
                    'color' => isset($package['ui']['color']) ? $package['ui']['color'] : '#3b82f6',
                    'icon' => isset($package['ui']['icon']) ? $package['ui']['icon'] : '',
                    'price' => isset($package['price']['amount']) ? floatval($package['price']['amount']) : 0,
                    'currency' => $this->get_currency(),
                    'duration' => isset($package['duration']) ? $package['duration'] : array('type' => 'days', 'amount' => 7),
                    'priority' => isset($package['priority']) ? intval($package['priority']) : 1,
                );
            }
        }

        return $filtered;
    }

    /**
     * Get currency from Voxel settings
     */
    private function get_currency() {
        // Try to get from Voxel settings
        if (function_exists('\Voxel\get') && method_exists('\Voxel\Stripe', 'get_currency')) {
            return \Voxel\Stripe::get_currency();
        }

        // Fallback
        return 'USD';
    }

    /**
     * Format price with currency
     */
    private function format_price($amount, $currency = null) {
        if ($currency === null) {
            $currency = $this->get_currency();
        }

        // Use Voxel's price formatting if available
        if (function_exists('\Voxel\Stripe::format_amount')) {
            return \Voxel\Stripe::format_amount($amount);
        }

        // Simple fallback formatting
        $symbol = '$';
        $symbols = array(
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'AUD' => 'A$',
            'CAD' => 'C$',
        );

        if (isset($symbols[strtoupper($currency)])) {
            $symbol = $symbols[strtoupper($currency)];
        }

        return $symbol . number_format($amount, 2);
    }

    /**
     * AJAX handler to get promotion packages for a post type
     */
    public function ajax_get_packages() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'vt_promotion_packages')) {
            wp_send_json_error(array('message' => __('Invalid security token', 'voxel-toolkit')));
        }

        $post_type = isset($_POST['post_type']) ? sanitize_text_field($_POST['post_type']) : '';

        if (empty($post_type)) {
            wp_send_json_error(array('message' => __('Post type is required', 'voxel-toolkit')));
        }

        $packages = $this->get_packages_for_post_type($post_type);

        // Format prices
        foreach ($packages as &$package) {
            $package['formatted_price'] = $this->format_price($package['price'], $package['currency']);
        }

        wp_send_json_success(array(
            'packages' => $packages,
        ));
    }

    /**
     * Render frontend JavaScript
     */
    public function render_frontend_script() {
        // Only on frontend
        if (is_admin()) {
            return;
        }

        // Don't output if no widgets with promotions enabled
        if (empty($this->promotion_widgets)) {
            return;
        }

        $nonce = wp_create_nonce('vt_promotion_packages');
        $ajax_url = admin_url('admin-ajax.php');
        $widgets_config = wp_json_encode($this->promotion_widgets);
        ?>
        <script>
        (function() {
            'use strict';

            // Widget configurations from PHP
            const vtPromotionWidgets = <?php echo $widgets_config; ?>;

            // Store selected promotion globally
            window.vtSelectedPromotion = null;

            // Initialize when DOM is ready
            function initPromotionSelector() {
                // Find all create-post widgets
                const widgets = document.querySelectorAll('.elementor-widget-ts-create-post');

                widgets.forEach(widget => {
                    // Get the widget ID from the element
                    const widgetId = widget.dataset.id;
                    const vtSettings = vtPromotionWidgets[widgetId];

                    if (!vtSettings || !vtSettings.enabled) {
                        return;
                    }

                    // Get the form element
                    const form = widget.querySelector('.ts-create-post');
                    if (!form) return;

                    // Get config from vxconfig script
                    const configScript = widget.querySelector('script.vxconfig');
                    let config = {};
                    if (configScript) {
                        try {
                            config = JSON.parse(configScript.textContent || '{}');
                        } catch (e) {}
                    }

                    const postType = config.post_type?.key || '';
                    if (!postType) return;

                    // Configuration from PHP-captured settings
                    const vtConfig = {
                        title: vtSettings.title || '<?php echo esc_js(__('Boost your listing', 'voxel-toolkit')); ?>',
                        description: vtSettings.description || '<?php echo esc_js(__('Get more visibility with a promotion package (optional)', 'voxel-toolkit')); ?>',
                        skipText: vtSettings.skip_text || '<?php echo esc_js(__('No thanks, just submit', 'voxel-toolkit')); ?>',
                    };

                    // Create and inject the promotion selector
                    const selector = createPromotionSelector(vtConfig, postType);
                    const formFooter = form.querySelector('.ts-form-footer');

                    if (formFooter && !form.querySelector('.vt-promotion-selector-container')) {
                        formFooter.parentNode.insertBefore(selector, formFooter);

                        // Load packages
                        loadPackages(selector, postType);

                        // Watch for step changes to show/hide
                        observeStepChanges(form, selector, config);
                    }
                });
            }

            function createPromotionSelector(vtConfig, postType) {
                const container = document.createElement('div');
                container.className = 'vt-promotion-selector-container';
                container.style.display = 'none'; // Hidden by default until last step
                container.dataset.postType = postType;

                container.innerHTML = `
                    <div class="vt-promotion-selector">
                        <div class="vt-promotion-header">
                            <div class="vt-promotion-icon-header">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                                </svg>
                            </div>
                            <div class="vt-promotion-header-text">
                                <h3>${escapeHtml(vtConfig.title)}</h3>
                                <p>${escapeHtml(vtConfig.description)}</p>
                            </div>
                        </div>

                        <div class="vt-promotion-packages">
                            <div class="vt-promotion-loading">
                                <span class="vt-spinner"></span>
                                <span><?php echo esc_js(__('Loading packages...', 'voxel-toolkit')); ?></span>
                            </div>
                        </div>

                        <a href="#" class="vt-promotion-skip">${escapeHtml(vtConfig.skipText)}</a>
                    </div>
                `;

                // Skip button click handler
                container.querySelector('.vt-promotion-skip').addEventListener('click', function(e) {
                    e.preventDefault();
                    window.vtSelectedPromotion = null;
                    // Deselect all cards
                    container.querySelectorAll('.vt-promotion-card').forEach(card => {
                        card.classList.remove('is-selected');
                    });
                });

                return container;
            }

            function loadPackages(container, postType) {
                const packagesContainer = container.querySelector('.vt-promotion-packages');

                const formData = new FormData();
                formData.append('action', 'vt_get_promotion_packages');
                formData.append('post_type', postType);
                formData.append('nonce', '<?php echo esc_js($nonce); ?>');

                fetch('<?php echo esc_js($ajax_url); ?>', {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.data.packages && data.data.packages.length > 0) {
                        packagesContainer.innerHTML = '';

                        data.data.packages.forEach(pkg => {
                            const card = createPackageCard(pkg);
                            packagesContainer.appendChild(card);
                        });
                    } else {
                        // No packages - hide the entire selector
                        container.style.display = 'none';
                        container.dataset.noPackages = 'true';
                    }
                })
                .catch(err => {
                    console.error('Error loading promotion packages:', err);
                    container.style.display = 'none';
                });
            }

            function createPackageCard(pkg) {
                const card = document.createElement('div');
                card.className = 'vt-promotion-card';
                card.dataset.packageKey = pkg.key;
                card.style.setProperty('--vt-accent-color', pkg.color || '#3b82f6');

                const duration = formatDuration(pkg.duration);

                card.innerHTML = `
                    <div class="vt-promotion-card-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                        </svg>
                    </div>
                    <div class="vt-promotion-card-info">
                        <div class="vt-promotion-card-label">${escapeHtml(pkg.label)}</div>
                        ${pkg.description ? `<div class="vt-promotion-card-desc">${escapeHtml(pkg.description)}</div>` : ''}
                        <div class="vt-promotion-card-duration">${duration}</div>
                    </div>
                    <div class="vt-promotion-card-price">${escapeHtml(pkg.formatted_price)}</div>
                    <div class="vt-promotion-card-check">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="20 6 9 17 4 12"></polyline>
                        </svg>
                    </div>
                `;

                card.addEventListener('click', function() {
                    const isSelected = card.classList.contains('is-selected');

                    // Deselect all cards
                    card.parentNode.querySelectorAll('.vt-promotion-card').forEach(c => {
                        c.classList.remove('is-selected');
                    });

                    if (isSelected) {
                        window.vtSelectedPromotion = null;
                    } else {
                        card.classList.add('is-selected');
                        window.vtSelectedPromotion = pkg;
                    }
                });

                return card;
            }

            function formatDuration(duration) {
                if (!duration) return '';
                const amount = duration.amount || 7;
                const type = duration.type || 'days';

                if (type === 'days') {
                    return amount === 1 ? '<?php echo esc_js(__('1 day', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('days', 'voxel-toolkit')); ?>';
                } else if (type === 'weeks') {
                    return amount === 1 ? '<?php echo esc_js(__('1 week', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('weeks', 'voxel-toolkit')); ?>';
                } else if (type === 'months') {
                    return amount === 1 ? '<?php echo esc_js(__('1 month', 'voxel-toolkit')); ?>' : amount + ' <?php echo esc_js(__('months', 'voxel-toolkit')); ?>';
                }
                return '';
            }

            function observeStepChanges(form, selector, config) {
                // Get total steps from progress indicators
                const updateVisibility = () => {
                    if (selector.dataset.noPackages === 'true') return;

                    const stepIndicators = form.querySelectorAll('.step-percentage li');
                    const doneSteps = form.querySelectorAll('.step-percentage li.step-done');

                    // Check if on last step - look for visible submit button
                    const submitBtn = form.querySelector('.ts-save-changes:not([style*="display: none"])');
                    const nextBtn = form.querySelector('.ts-next');
                    const isLastStep = submitBtn && (!nextBtn || nextBtn.classList.contains('disabled'));

                    // Also check by step count
                    const totalSteps = stepIndicators.length;
                    const currentStep = doneSteps.length;

                    if (isLastStep || currentStep >= totalSteps) {
                        selector.style.display = 'block';
                    } else {
                        selector.style.display = 'none';
                    }
                };

                // Initial check
                setTimeout(updateVisibility, 500);

                // Watch for DOM changes (step navigation)
                const observer = new MutationObserver(() => {
                    updateVisibility();
                });

                observer.observe(form, {
                    childList: true,
                    subtree: true,
                    attributes: true,
                    attributeFilter: ['class', 'style']
                });

                // Also listen for click events on navigation buttons
                form.addEventListener('click', function(e) {
                    if (e.target.closest('.ts-next, .ts-prev, .step-percentage li')) {
                        setTimeout(updateVisibility, 100);
                    }
                });
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Hook into form submission to redirect to checkout
            function setupSubmitInterception() {
                document.addEventListener('click', function(e) {
                    const submitBtn = e.target.closest('.ts-save-changes');
                    if (!submitBtn || !window.vtSelectedPromotion) return;

                    // Store the selected promotion to use after submission
                    const selectedPromotion = window.vtSelectedPromotion;

                    // Watch for success screen
                    const form = submitBtn.closest('.ts-create-post');
                    if (!form) return;

                    const observer = new MutationObserver((mutations, obs) => {
                        const successScreen = form.querySelector('.ts-edit-success');
                        if (successScreen) {
                            obs.disconnect();

                            // Get the post ID from the edit/back link (format: ?post_id=123)
                            const editLink = successScreen.querySelector('a[href*="post_id="]');
                            let postId = null;

                            if (editLink) {
                                const match = editLink.href.match(/[?&]post_id=(\d+)/);
                                if (match) postId = match[1];
                            }

                            if (postId && selectedPromotion) {
                                redirectToPromoCheckout(postId, selectedPromotion.key);
                            }
                        }
                    });

                    observer.observe(form, { childList: true, subtree: true });

                    // Disconnect after timeout
                    setTimeout(() => observer.disconnect(), 30000);
                });
            }

            function redirectToPromoCheckout(postId, promotionKey) {
                // Redirect to cart-summary page with promotion parameters
                // Voxel will handle the checkout flow from there
                const cartUrl = new URL('<?php echo esc_js(home_url('/cart-summary/')); ?>');
                cartUrl.searchParams.set('screen', 'promote');
                cartUrl.searchParams.set('post_id', postId);
                cartUrl.searchParams.set('package', promotionKey);

                window.location.href = cartUrl.toString();
            }

            // Initialize
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => {
                    initPromotionSelector();
                    setupSubmitInterception();
                });
            } else {
                initPromotionSelector();
                setupSubmitInterception();
            }

            // Also re-init on Elementor frontend init (for preview)
            document.addEventListener('elementor/init', initPromotionSelector);
        })();
        </script>
        <?php
    }

    /**
     * Render settings section (for admin settings page)
     */
    public static function render_settings() {
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Promotion Create Form', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <p class="description">
                    <?php _e('This feature adds promotion package selection to the Create Form widget.', 'voxel-toolkit'); ?>
                </p>
                <ul style="list-style: disc; margin-left: 20px; margin-top: 10px;">
                    <li><?php _e('Enable promotions per widget in Elementor settings', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Packages are filtered by post type automatically', 'voxel-toolkit'); ?></li>
                    <li><?php _e('Users are redirected to checkout after post submission', 'voxel-toolkit'); ?></li>
                </ul>
            </td>
        </tr>
        <?php
    }
}
