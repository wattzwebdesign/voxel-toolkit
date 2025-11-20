<?php
/**
 * Campaign Progress Widget
 *
 * Elementor widget for displaying donation/crowdfunding progress
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Campaign_Progress_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-campaign-progress';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Campaign Progress', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-product-rating';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content controls
     */
    private function register_content_controls() {
        // Settings Section
        $this->start_controls_section(
            'section_settings',
            [
                'label' => __('Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'goal_amount',
            [
                'label' => __('Goal Amount', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 510,
                'min' => 0,
            ]
        );

        $this->add_control(
            'currency_symbol',
            [
                'label' => __('Currency Symbol', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '£',
            ]
        );

        $this->add_control(
            'display_all_data',
            [
                'label' => __('Display All Data', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'display_donated_vs_goal',
            [
                'label' => __('Display Donated vs Goal', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'display_all_data!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'display_progress_bar',
            [
                'label' => __('Display Progress Bar', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'display_all_data!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'display_donation_count',
            [
                'label' => __('Display Number of Donations', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'display_all_data!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'display_donor_list',
            [
                'label' => __('Display Donor List', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => [
                    'display_all_data!' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'donors_to_show',
            [
                'label' => __('Number of Donors to Show', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 20,
                'condition' => [
                    'display_donor_list' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();

        // Text Labels Section
        $this->start_controls_section(
            'section_labels',
            [
                'label' => __('Text Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'label_donation_value',
            [
                'label' => __('Donation Value Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Donation value:',
            ]
        );

        $this->add_control(
            'label_left_of',
            [
                'label' => __('Left Of Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'left of',
            ]
        );

        $this->add_control(
            'label_goal',
            [
                'label' => __('Goal Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Goal',
            ]
        );

        $this->add_control(
            'label_donated',
            [
                'label' => __('Donated Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Donated',
            ]
        );

        $this->add_control(
            'label_donation',
            [
                'label' => __('Donation Label (singular)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Donation',
            ]
        );

        $this->add_control(
            'label_donations',
            [
                'label' => __('Donations Label (plural)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Donations',
            ]
        );

        $this->add_control(
            'label_donor_name',
            [
                'label' => __('Donor Name Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Platform',
                'description' => __('Default name when no donor info available', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Progress Bar Style
        $this->start_controls_section(
            'section_progress_bar_style',
            [
                'label' => __('Progress Bar', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'progress_bar_height',
            [
                'label' => __('Height', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 40,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-campaign-progress-bar' => 'height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#f0f0f0',
                'selectors' => [
                    '{{WRAPPER}} .vt-campaign-progress-bar' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_fill_color',
            [
                'label' => __('Fill Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#9ACD8F',
                'selectors' => [
                    '{{WRAPPER}} .vt-campaign-progress-fill' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'progress_bar_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 4,
                    'right' => 4,
                    'bottom' => 4,
                    'left' => 4,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-campaign-progress-bar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .vt-campaign-progress-fill' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Donor List Style
        $this->start_controls_section(
            'section_donor_list_style',
            [
                'label' => __('Donor List', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'display_donor_list' => 'yes',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'donor_item_border',
                'selector' => '{{WRAPPER}} .vt-donor-item',
            ]
        );

        $this->add_control(
            'donor_item_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-item' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'donor_item_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 12,
                    'right' => 12,
                    'bottom' => 12,
                    'left' => 12,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-item' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'donor_item_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 0,
                    'right' => 0,
                    'bottom' => 8,
                    'left' => 0,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-item' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'donor_item_box_shadow',
                'selector' => '{{WRAPPER}} .vt-donor-item',
            ]
        );

        $this->add_control(
            'donor_item_bg_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-item' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Typography
        $this->start_controls_section(
            'section_typography',
            [
                'label' => __('Typography', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'progress_text_typography',
                'label' => __('Progress Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-campaign-progress-text',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'donor_name_typography',
                'label' => __('Donor Name', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-donor-name',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'donor_meta_typography',
                'label' => __('Donor Meta (Date/Amount)', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-donor-date, {{WRAPPER}} .vt-donor-amount',
            ]
        );

        $this->end_controls_section();

        // Colors
        $this->start_controls_section(
            'section_colors',
            [
                'label' => __('Colors', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'progress_text_color',
            [
                'label' => __('Progress Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-campaign-progress-text' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'donor_name_color',
            [
                'label' => __('Donor Name Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-name' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'donor_meta_color',
            [
                'label' => __('Donor Meta Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666666',
                'selectors' => [
                    '{{WRAPPER}} .vt-donor-date, {{WRAPPER}} .vt-donor-amount' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        // Get campaign progress data
        $progress = \Voxel_Toolkit_Campaign_Progress_Widget_Manager::get_campaign_progress($post_id);

        $goal = floatval($settings['goal_amount']);
        $raised = $progress['total_raised'];
        $remaining = max(0, $goal - $raised);
        $percentage = $goal > 0 ? min(100, ($raised / $goal) * 100) : 0;
        $currency = $settings['currency_symbol'];

        $donation_label = $progress['donation_count'] === 1 ?
            $settings['label_donation'] :
            $settings['label_donations'];
        ?>

        <div class="vt-campaign-progress-widget">

            <!-- Progress Summary -->
            <?php if ($settings['display_all_data'] === 'yes' || $settings['display_donated_vs_goal'] === 'yes') : ?>
                <div class="vt-campaign-progress-summary">
                    <p class="vt-campaign-progress-text">
                        <?php echo esc_html($currency . number_format($remaining, 0)); ?>
                        <?php echo esc_html($settings['label_left_of']); ?>
                        <?php echo esc_html($settings['label_goal']); ?>
                        <?php echo esc_html($currency . number_format($goal, 0)); ?>
                    </p>
                </div>
            <?php endif; ?>

            <!-- Progress Bar -->
            <?php if ($settings['display_all_data'] === 'yes' || $settings['display_progress_bar'] === 'yes') : ?>
                <div class="vt-campaign-progress-bar">
                    <div class="vt-campaign-progress-fill" style="width: <?php echo esc_attr($percentage); ?>%;">
                        <span class="vt-campaign-progress-amount">
                            <?php echo esc_html($currency . number_format($raised, 0)); ?>
                            <?php echo esc_html($settings['label_donated']); ?>
                        </span>
                        <span class="vt-campaign-progress-percentage">
                            <?php echo esc_html(round($percentage)); ?>%
                        </span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Donation Count -->
            <?php if ($settings['display_all_data'] === 'yes' || $settings['display_donation_count'] === 'yes') : ?>
                <div class="vt-campaign-donation-count">
                    <strong><?php echo esc_html($progress['donation_count']); ?></strong>
                    <?php echo esc_html($donation_label); ?>
                </div>
            <?php endif; ?>

            <!-- Donor List -->
            <?php if (($settings['display_all_data'] === 'yes' || $settings['display_donor_list'] === 'yes') && !empty($progress['recent_donors'])) : ?>
                <div class="vt-donor-list">
                    <?php
                    $donors_to_show = intval($settings['donors_to_show']);
                    $donors = array_slice($progress['recent_donors'], 0, $donors_to_show);

                    foreach ($donors as $donor) :
                        $donor_name = !empty($donor['name']) ? $donor['name'] : $settings['label_donor_name'];
                        $donor_date = date('M j, Y', strtotime($donor['date']));
                        $donor_amount = $currency . number_format($donor['amount'], 0);
                    ?>
                        <div class="vt-donor-item">
                            <div class="vt-donor-avatar">
                                <img src="<?php echo esc_url($donor['avatar_url']); ?>" alt="<?php echo esc_attr($donor_name); ?>">
                            </div>
                            <div class="vt-donor-info">
                                <div class="vt-donor-name"><?php echo esc_html($donor_name); ?></div>
                                <div class="vt-donor-date"><?php echo esc_html($donor_date); ?></div>
                            </div>
                            <div class="vt-donor-amount"><?php echo esc_html($donor_amount); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>

        <?php
    }
}
