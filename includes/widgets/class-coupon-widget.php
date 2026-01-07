<?php
/**
 * Coupon Widget Elementor Widget
 *
 * Stripe coupon management widget for Elementor
 *
 * @package Voxel_Toolkit
 * @since 1.6.1
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class Voxel_Toolkit_Coupon_Widget
 *
 * Elementor widget for Stripe coupon management
 */
class Voxel_Toolkit_Coupon_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'voxel-coupon-manager';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Coupon Manager (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-price-table';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories() {
        return array('voxel-toolkit', 'general');
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords() {
        return array('coupon', 'discount', 'stripe', 'promo', 'code');
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {

        // Content Section - Labels
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'form_heading',
            array(
                'label' => __('Form Heading', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Create Coupon', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'list_heading',
            array(
                'label' => __('List Heading', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Your Coupons', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'submit_button_text',
            array(
                'label' => __('Submit Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Create Coupon', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'show_form',
            array(
                'label' => __('Show Create Form', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'show_list',
            array(
                'label' => __('Show Coupon List', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'login_message',
            array(
                'label' => __('Login Required Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Please log in to manage coupons.', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'stripe_required_message',
            array(
                'label' => __('Stripe Not Enabled Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Stripe payment gateway must be enabled to use this feature.', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->end_controls_section();

        // Style Section - Container
        $this->start_controls_section(
            'container_style_section',
            array(
                'label' => __('Container', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'container_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'container_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-widget' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .vt-coupon-widget',
            )
        );

        $this->add_responsive_control(
            'container_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Headings
        $this->start_controls_section(
            'heading_style_section',
            array(
                'label' => __('Headings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'heading_color',
            array(
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-heading' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'heading_typography',
                'selector' => '{{WRAPPER}} .vt-coupon-heading',
            )
        );

        $this->add_responsive_control(
            'heading_spacing',
            array(
                'label' => __('Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 50,
                    ),
                ),
                'default' => array(
                    'size' => 15,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Form
        $this->start_controls_section(
            'form_style_section',
            array(
                'label' => __('Form', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'label_color',
            array(
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form label' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'label_typography',
                'label' => __('Label Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-coupon-form label',
            )
        );

        $this->add_control(
            'input_bg',
            array(
                'label' => __('Input Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form input, {{WRAPPER}} .vt-coupon-form select' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'input_color',
            array(
                'label' => __('Input Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form input, {{WRAPPER}} .vt-coupon-form select' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'input_border_color',
            array(
                'label' => __('Input Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form input, {{WRAPPER}} .vt-coupon-form select' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'input_border_radius',
            array(
                'label' => __('Input Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 20,
                    ),
                ),
                'default' => array(
                    'size' => 4,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form input, {{WRAPPER}} .vt-coupon-form select' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'input_padding',
            array(
                'label' => __('Input Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default' => array(
                    'top' => 10,
                    'right' => 12,
                    'bottom' => 10,
                    'left' => 12,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-form input, {{WRAPPER}} .vt-coupon-form select' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Button
        $this->start_controls_section(
            'button_style_section',
            array(
                'label' => __('Submit Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .vt-coupon-submit',
            )
        );

        $this->add_responsive_control(
            'button_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default' => array(
                    'top' => 12,
                    'right' => 24,
                    'bottom' => 12,
                    'left' => 24,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'default' => array(
                    'size' => 4,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->start_controls_tabs('button_tabs');

        $this->start_controls_tab(
            'button_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#635bff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'button_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'button_hover_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'button_hover_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#4b44c7',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-submit:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Style Section - Coupon List
        $this->start_controls_section(
            'list_style_section',
            array(
                'label' => __('Coupon List', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'card_bg',
            array(
                'label' => __('Card Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f7f7f7',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'card_border',
                'selector' => '{{WRAPPER}} .vt-coupon-item',
            )
        );

        $this->add_responsive_control(
            'card_border_radius',
            array(
                'label' => __('Card Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 20,
                    ),
                ),
                'default' => array(
                    'size' => 8,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_padding',
            array(
                'label' => __('Card Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default' => array(
                    'top' => 15,
                    'right' => 15,
                    'bottom' => 15,
                    'left' => 15,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'card_spacing',
            array(
                'label' => __('Card Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'default' => array(
                    'size' => 10,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item + .vt-coupon-item' => 'margin-top: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'coupon_name_color',
            array(
                'label' => __('Coupon Name Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-name' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'coupon_name_typography',
                'label' => __('Coupon Name Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-coupon-name',
            )
        );

        $this->add_control(
            'coupon_details_color',
            array(
                'label' => __('Details Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-details' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'discount_badge_bg',
            array(
                'label' => __('Discount Badge Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#635bff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-discount' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'discount_badge_color',
            array(
                'label' => __('Discount Badge Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-discount' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Delete Button
        $this->start_controls_section(
            'delete_button_style_section',
            array(
                'label' => __('Delete Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->start_controls_tabs('delete_button_tabs');

        $this->start_controls_tab(
            'delete_button_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'delete_button_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3232',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-delete' => 'color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'delete_button_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'delete_button_hover_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-delete:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'delete_button_hover_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3232',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-delete:hover' => 'background-color: {{VALUE}}; border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Style Section - Expired Coupons
        $this->start_controls_section(
            'expired_style_section',
            array(
                'label' => __('Expired Coupons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'expired_opacity',
            array(
                'label' => __('Opacity', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                    ),
                ),
                'default' => array(
                    'size' => 0.7,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item.vt-coupon-expired' => 'opacity: {{SIZE}};',
                ),
            )
        );

        $this->add_control(
            'expired_card_bg',
            array(
                'label' => __('Card Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f5f5f5',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item.vt-coupon-expired' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'expired_badge_bg',
            array(
                'label' => __('Discount Badge Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999999',
                'selectors' => array(
                    '{{WRAPPER}} .vt-coupon-item.vt-coupon-expired .vt-coupon-discount' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'expired_text_color',
            array(
                'label' => __('Expired Badge Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3232',
                'selectors' => array(
                    '{{WRAPPER}} .vt-expired-badge' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Promo Codes
        $this->start_controls_section(
            'promo_code_style_section',
            array(
                'label' => __('Promo Codes', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'promo_code_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#e9e9e9',
                'selectors' => array(
                    '{{WRAPPER}} .vt-promo-code' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'promo_code_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .vt-promo-code' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_responsive_control(
            'promo_code_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'default' => array(
                    'top' => 4,
                    'right' => 10,
                    'bottom' => 4,
                    'left' => 10,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-promo-code' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'promo_code_border_radius',
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
                'default' => array(
                    'size' => 4,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-promo-code' => 'border-radius: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Check if Stripe is the enabled payment provider
     *
     * @return bool
     */
    private function is_stripe_enabled() {
        $provider = \Voxel\get('payments.provider', '');
        return $provider === 'stripe';
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Check if Stripe is enabled
        if (!$this->is_stripe_enabled()) {
            echo '<div class="vt-coupon-widget vt-coupon-login-required">';
            echo '<p>' . esc_html($settings['stripe_required_message']) . '</p>';
            echo '</div>';
            return;
        }

        // Check if user is logged in
        if (!is_user_logged_in()) {
            echo '<div class="vt-coupon-widget vt-coupon-login-required">';
            echo '<p>' . esc_html($settings['login_message']) . '</p>';
            echo '</div>';
            return;
        }

        ?>
        <div class="vt-coupon-widget">
            <?php if ($settings['show_form'] === 'yes') : ?>
            <div class="vt-coupon-form-section">
                <?php if (!empty($settings['form_heading'])) : ?>
                    <h3 class="vt-coupon-heading"><?php echo esc_html($settings['form_heading']); ?></h3>
                <?php endif; ?>

                <form class="vt-coupon-form">
                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-name"><?php esc_html_e('Coupon Name', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="text" id="vt-coupon-name" name="name" required>
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-code"><?php esc_html_e('Promo Code (optional)', 'voxel-toolkit'); ?></label>
                            <input type="text" id="vt-coupon-code" name="code" placeholder="<?php esc_attr_e('Leave blank for auto-generated', 'voxel-toolkit'); ?>">
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-third">
                            <label for="vt-coupon-discount-type"><?php esc_html_e('Discount Type', 'voxel-toolkit'); ?></label>
                            <select id="vt-coupon-discount-type" name="discount_type">
                                <option value="percent"><?php esc_html_e('Percent Off', 'voxel-toolkit'); ?></option>
                                <option value="fixed"><?php esc_html_e('Fixed Amount', 'voxel-toolkit'); ?></option>
                            </select>
                        </div>
                        <div class="vt-form-group vt-form-group-third vt-discount-percent">
                            <label for="vt-coupon-percent-off"><?php esc_html_e('Percent Off', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="number" id="vt-coupon-percent-off" name="percent_off" min="1" max="100" placeholder="10">
                        </div>
                        <div class="vt-form-group vt-form-group-third vt-discount-fixed" style="display: none;">
                            <label for="vt-coupon-amount-off"><?php esc_html_e('Amount Off', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="number" id="vt-coupon-amount-off" name="amount_off" min="0.01" step="0.01" placeholder="5.00">
                        </div>
                        <div class="vt-form-group vt-form-group-third">
                            <label for="vt-coupon-duration"><?php esc_html_e('Duration', 'voxel-toolkit'); ?></label>
                            <select id="vt-coupon-duration" name="duration">
                                <option value="once"><?php esc_html_e('Once', 'voxel-toolkit'); ?></option>
                                <option value="repeating"><?php esc_html_e('Repeating', 'voxel-toolkit'); ?></option>
                                <option value="forever"><?php esc_html_e('Forever', 'voxel-toolkit'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="vt-form-row vt-duration-months-row" style="display: none;">
                        <div class="vt-form-group">
                            <label for="vt-coupon-duration-months"><?php esc_html_e('Duration (months)', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="number" id="vt-coupon-duration-months" name="duration_months" min="1" placeholder="3">
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-max-redemptions"><?php esc_html_e('Max Redemptions (optional)', 'voxel-toolkit'); ?></label>
                            <input type="number" id="vt-coupon-max-redemptions" name="max_redemptions" min="1" placeholder="<?php esc_attr_e('Unlimited', 'voxel-toolkit'); ?>">
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-redeem-by"><?php esc_html_e('Expiration Date (optional)', 'voxel-toolkit'); ?></label>
                            <input type="text" id="vt-coupon-redeem-by" name="redeem_by" placeholder="<?php esc_attr_e('Select date', 'voxel-toolkit'); ?>" autocomplete="off">
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-min-amount"><?php esc_html_e('Minimum Order Value (optional)', 'voxel-toolkit'); ?></label>
                            <input type="number" id="vt-coupon-min-amount" name="minimum_amount" min="0" step="0.01" placeholder="<?php esc_attr_e('No minimum', 'voxel-toolkit'); ?>">
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label for="vt-coupon-customer-email"><?php esc_html_e('Limit to Customer Email (optional)', 'voxel-toolkit'); ?></label>
                            <input type="email" id="vt-coupon-customer-email" name="customer_email" placeholder="<?php esc_attr_e('Any customer', 'voxel-toolkit'); ?>">
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-checkbox">
                            <label>
                                <input type="checkbox" id="vt-coupon-first-time" name="first_time_only">
                                <?php esc_html_e('First-time customers only', 'voxel-toolkit'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <button type="submit" class="vt-coupon-submit">
                            <?php echo esc_html($settings['submit_button_text']); ?>
                        </button>
                    </div>

                    <div class="vt-coupon-message" style="display: none;"></div>
                </form>
            </div>
            <?php endif; ?>

            <?php if ($settings['show_list'] === 'yes') : ?>
            <div class="vt-coupon-list-section">
                <?php if (!empty($settings['list_heading'])) : ?>
                    <h3 class="vt-coupon-heading"><?php echo esc_html($settings['list_heading']); ?></h3>
                <?php endif; ?>

                <div class="vt-coupon-list">
                    <div class="vt-coupon-loading"><?php esc_html_e('Loading coupons...', 'voxel-toolkit'); ?></div>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <div class="vt-coupon-widget">
            <# if (settings.show_form === 'yes') { #>
            <div class="vt-coupon-form-section">
                <# if (settings.form_heading) { #>
                    <h3 class="vt-coupon-heading">{{{ settings.form_heading }}}</h3>
                <# } #>

                <form class="vt-coupon-form">
                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Coupon Name', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="text" disabled>
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Promo Code (optional)', 'voxel-toolkit'); ?></label>
                            <input type="text" disabled>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-third">
                            <label><?php esc_html_e('Discount Type', 'voxel-toolkit'); ?></label>
                            <select disabled>
                                <option><?php esc_html_e('Percent Off', 'voxel-toolkit'); ?></option>
                            </select>
                        </div>
                        <div class="vt-form-group vt-form-group-third">
                            <label><?php esc_html_e('Percent Off', 'voxel-toolkit'); ?> <span class="required">*</span></label>
                            <input type="number" disabled>
                        </div>
                        <div class="vt-form-group vt-form-group-third">
                            <label><?php esc_html_e('Duration', 'voxel-toolkit'); ?></label>
                            <select disabled>
                                <option><?php esc_html_e('Once', 'voxel-toolkit'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Max Redemptions', 'voxel-toolkit'); ?></label>
                            <input type="number" disabled>
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Expiration Date', 'voxel-toolkit'); ?></label>
                            <input type="text" placeholder="<?php esc_attr_e('Select date', 'voxel-toolkit'); ?>" disabled>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Minimum Order Value', 'voxel-toolkit'); ?></label>
                            <input type="number" disabled>
                        </div>
                        <div class="vt-form-group vt-form-group-half">
                            <label><?php esc_html_e('Limit to Customer Email', 'voxel-toolkit'); ?></label>
                            <input type="email" disabled>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <div class="vt-form-group vt-form-checkbox">
                            <label>
                                <input type="checkbox" disabled>
                                <?php esc_html_e('First-time customers only', 'voxel-toolkit'); ?>
                            </label>
                        </div>
                    </div>

                    <div class="vt-form-row">
                        <button type="button" class="vt-coupon-submit">
                            {{{ settings.submit_button_text }}}
                        </button>
                    </div>
                </form>
            </div>
            <# } #>

            <# if (settings.show_list === 'yes') { #>
            <div class="vt-coupon-list-section">
                <# if (settings.list_heading) { #>
                    <h3 class="vt-coupon-heading">{{{ settings.list_heading }}}</h3>
                <# } #>

                <div class="vt-coupon-list">
                    <div class="vt-coupon-item">
                        <div class="vt-coupon-header">
                            <span class="vt-coupon-name"><?php esc_html_e('Sample Coupon', 'voxel-toolkit'); ?></span>
                            <span class="vt-coupon-discount">10% OFF</span>
                        </div>
                        <div class="vt-coupon-details">
                            <span><?php esc_html_e('Duration: Once', 'voxel-toolkit'); ?></span>
                            <span><?php esc_html_e('Redeemed: 0 times', 'voxel-toolkit'); ?></span>
                        </div>
                        <div class="vt-coupon-codes">
                            <span class="vt-promo-code">SAMPLE10</span>
                        </div>
                        <button type="button" class="vt-coupon-delete"><?php esc_html_e('Delete', 'voxel-toolkit'); ?></button>
                    </div>
                </div>
            </div>
            <# } #>
        </div>
        <?php
    }
}
