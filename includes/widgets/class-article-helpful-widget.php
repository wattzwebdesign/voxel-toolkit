<?php
/**
 * Article Helpful Elementor Widget
 *
 * "Was this Article Helpful?" widget for Elementor
 *
 * @package Voxel_Toolkit
 * @since 1.4.0
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

/**
 * Class Voxel_Toolkit_Article_Helpful_Widget
 *
 * Elementor widget for article helpful feedback
 */
class Voxel_Toolkit_Article_Helpful_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'voxel-article-helpful';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Article Helpful (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-favorite';
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
        return array('helpful', 'feedback', 'vote', 'article', 'rating');
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {

        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'heading_text',
            array(
                'label' => __('Heading Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Was this article helpful?', 'voxel-toolkit'),
                'placeholder' => __('Enter your heading text', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'yes_button_text',
            array(
                'label' => __('Yes Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Yes', 'voxel-toolkit'),
                'placeholder' => __('Yes', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'no_button_text',
            array(
                'label' => __('No Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No', 'voxel-toolkit'),
                'placeholder' => __('No', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'success_message',
            array(
                'label' => __('Success Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Thank you for your feedback!', 'voxel-toolkit'),
                'placeholder' => __('Enter success message', 'voxel-toolkit'),
                'label_block' => true,
            )
        );

        $this->add_control(
            'update_message',
            array(
                'label' => __('Vote Updated Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Your vote has been updated.', 'voxel-toolkit'),
                'placeholder' => __('Enter vote updated message', 'voxel-toolkit'),
                'label_block' => true,
                'description' => __('Message shown when user changes their vote', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'already_voted_message',
            array(
                'label' => __('Already Voted Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('You have already voted this way.', 'voxel-toolkit'),
                'placeholder' => __('Enter already voted message', 'voxel-toolkit'),
                'label_block' => true,
                'description' => __('Message shown when user clicks the same vote again', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'show_icon',
            array(
                'label' => __('Show Icons', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->end_controls_section();

        // Style Section - Heading
        $this->start_controls_section(
            'heading_style_section',
            array(
                'label' => __('Heading', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'heading_color',
            array(
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-heading' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'heading_typography',
                'selector' => '{{WRAPPER}} .voxel-article-helpful-heading',
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
                        'max' => 100,
                    ),
                ),
                'default' => array(
                    'size' => 15,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-heading' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'heading_align',
            array(
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'default' => 'center',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-heading' => 'text-align: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Style Section - Buttons
        $this->start_controls_section(
            'buttons_style_section',
            array(
                'label' => __('Buttons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'button_align',
            array(
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => array(
                    'left' => array(
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ),
                    'center' => array(
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ),
                    'right' => array(
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ),
                ),
                'default' => 'center',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-buttons' => 'justify-content: {{VALUE}};',
                ),
                'selectors_dictionary' => array(
                    'left' => 'flex-start',
                    'center' => 'center',
                    'right' => 'flex-end',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'button_typography',
                'selector' => '{{WRAPPER}} .voxel-helpful-btn',
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
                    'right' => 30,
                    'bottom' => 12,
                    'left' => 30,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_spacing',
            array(
                'label' => __('Spacing Between Buttons', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 100,
                    ),
                ),
                'default' => array(
                    'size' => 10,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-buttons' => 'gap: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'button_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%', 'em'),
                'default' => array(
                    'top' => 4,
                    'right' => 4,
                    'bottom' => 4,
                    'left' => 4,
                    'unit' => 'px',
                    'isLinked' => true,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        // Yes Button Colors
        $this->add_control(
            'yes_button_heading',
            array(
                'label' => __('Yes Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->start_controls_tabs('yes_button_tabs');

        $this->start_controls_tab(
            'yes_button_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'yes_button_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'yes_button_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#46b450',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'yes_button_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'yes_button_hover_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'yes_button_hover_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#399e42',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        // No Button Colors
        $this->add_control(
            'no_button_heading',
            array(
                'label' => __('No Button', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->start_controls_tabs('no_button_tabs');

        $this->start_controls_tab(
            'no_button_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'no_button_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'no_button_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#dc3232',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'no_button_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'no_button_hover_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'no_button_hover_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#c42525',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn:hover' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Style Section - Icons
        $this->start_controls_section(
            'icon_style_section',
            array(
                'label' => __('Icons', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'icon_size',
            array(
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 10,
                        'max' => 50,
                    ),
                ),
                'default' => array(
                    'size' => 18,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn .btn-icon' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_responsive_control(
            'icon_spacing',
            array(
                'label' => __('Icon Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array('px'),
                'range' => array(
                    'px' => array(
                        'min' => 0,
                        'max' => 30,
                    ),
                ),
                'default' => array(
                    'size' => 8,
                ),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn .btn-icon' => 'margin-right: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        // Yes Icon Color
        $this->add_control(
            'yes_icon_heading',
            array(
                'label' => __('Yes Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->start_controls_tabs('yes_icon_tabs');

        $this->start_controls_tab(
            'yes_icon_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'yes_icon_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn .btn-icon svg' => 'fill: {{VALUE}};',
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn .btn-icon svg path' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'yes_icon_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'yes_icon_hover_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn:hover .btn-icon svg' => 'fill: {{VALUE}};',
                    '{{WRAPPER}} .voxel-helpful-btn.yes-btn:hover .btn-icon svg path' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        // No Icon Color
        $this->add_control(
            'no_icon_heading',
            array(
                'label' => __('No Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            )
        );

        $this->start_controls_tabs('no_icon_tabs');

        $this->start_controls_tab(
            'no_icon_normal',
            array(
                'label' => __('Normal', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'no_icon_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn .btn-icon svg' => 'fill: {{VALUE}};',
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn .btn-icon svg path' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->start_controls_tab(
            'no_icon_hover',
            array(
                'label' => __('Hover', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'no_icon_hover_color',
            array(
                'label' => __('Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn:hover .btn-icon svg' => 'fill: {{VALUE}};',
                    '{{WRAPPER}} .voxel-helpful-btn.no-btn:hover .btn-icon svg path' => 'fill: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();

        // Style Section - Success Message
        $this->start_controls_section(
            'success_style_section',
            array(
                'label' => __('Success Message', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'success_color',
            array(
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#46b450',
                'selectors' => array(
                    '{{WRAPPER}} .voxel-helpful-success' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'success_typography',
                'selector' => '{{WRAPPER}} .voxel-helpful-success',
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
                    '{{WRAPPER}} .voxel-article-helpful-wrapper' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'container_bg',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-wrapper' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .voxel-article-helpful-wrapper',
            )
        );

        $this->add_control(
            'container_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .voxel-article-helpful-wrapper' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output on the frontend
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = get_the_ID();

        // Check if user already voted
        $user_id = get_current_user_id();
        $previous_vote = null;

        if ($user_id) {
            // Check user meta
            $vote_meta_key = '_article_helpful_vote_' . $post_id;
            $previous_vote = get_user_meta($user_id, $vote_meta_key, true);
        } else {
            // Check cookie
            $cookie_name = 'voxel_helpful_' . $post_id;
            if (isset($_COOKIE[$cookie_name])) {
                $previous_vote = $_COOKIE[$cookie_name];
            }
        }

        ?>
        <div class="voxel-article-helpful-wrapper"
             data-post-id="<?php echo esc_attr($post_id); ?>"
             data-user-vote="<?php echo esc_attr($previous_vote ? $previous_vote : ''); ?>"
             data-already-voted-message="<?php echo esc_attr($settings['already_voted_message']); ?>">

            <div class="voxel-article-helpful-content">
                <div class="voxel-article-helpful-heading">
                    <?php echo esc_html($settings['heading_text']); ?>
                </div>

                <div class="voxel-article-helpful-buttons">
                        <button class="voxel-helpful-btn yes-btn<?php echo $previous_vote === 'yes' ? ' active' : ''; ?>" data-vote="yes">
                            <?php if ($settings['show_icon'] === 'yes') : ?>
                                <span class="btn-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path d="M22.773,7.721A4.994,4.994,0,0,0,19,6H15.011l.336-2.041A3.037,3.037,0,0,0,9.626,2.122L7.712,6H5a5.006,5.006,0,0,0-5,5v5a5.006,5.006,0,0,0,5,5H18.3a5.024,5.024,0,0,0,4.951-4.3l.705-5A5,5,0,0,0,22.773,7.721ZM2,16V11A3,3,0,0,1,5,8H7V19H5A3,3,0,0,1,2,16Zm19.971-4.581-.706,5A3.012,3.012,0,0,1,18.3,19H9V7.734a1,1,0,0,0,.23-.292l2.189-4.435A1.07,1.07,0,0,1,13.141,2.8a1.024,1.024,0,0,1,.233.84l-.528,3.2A1,1,0,0,0,13.833,8H19a3,3,0,0,1,2.971,3.419Z"/>
                                    </svg>
                                </span>
                            <?php endif; ?>
                            <span class="btn-text"><?php echo esc_html($settings['yes_button_text']); ?></span>
                        </button>

                        <button class="voxel-helpful-btn no-btn<?php echo $previous_vote === 'no' ? ' active' : ''; ?>" data-vote="no">
                            <?php if ($settings['show_icon'] === 'yes') : ?>
                                <span class="btn-icon">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                        <path d="M23.951,12.3l-.705-5A5.024,5.024,0,0,0,18.3,3H5A5.006,5.006,0,0,0,0,8v5a5.006,5.006,0,0,0,5,5H7.712l1.914,3.878a3.037,3.037,0,0,0,5.721-1.837L15.011,18H19a5,5,0,0,0,4.951-5.7ZM5,5H7V16H5a3,3,0,0,1-3-3V8A3,3,0,0,1,5,5Zm16.264,9.968A3,3,0,0,1,19,16H13.833a1,1,0,0,0-.987,1.162l.528,3.2a1.024,1.024,0,0,1-.233.84,1.07,1.07,0,0,1-1.722-.212L9.23,16.558A1,1,0,0,0,9,16.266V5h9.3a3.012,3.012,0,0,1,2.97,2.581l.706,5A3,3,0,0,1,21.264,14.968Z"/>
                                    </svg>
                                </span>
                            <?php endif; ?>
                            <span class="btn-text"><?php echo esc_html($settings['no_button_text']); ?></span>
                        </button>
                    </div>
                </div>

            <div class="voxel-helpful-success" style="display: none;"
                 data-success-message="<?php echo esc_attr($settings['success_message']); ?>"
                 data-update-message="<?php echo esc_attr($settings['update_message']); ?>">
                <?php echo esc_html($settings['success_message']); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render widget output in the editor (Elementor preview)
     */
    protected function content_template() {
        ?>
        <#
        var postId = '<?php echo get_the_ID(); ?>';
        #>
        <div class="voxel-article-helpful-wrapper" data-post-id="{{ postId }}">
            <div class="voxel-article-helpful-content">
                <div class="voxel-article-helpful-heading">
                    {{{ settings.heading_text }}}
                </div>

                <div class="voxel-article-helpful-buttons">
                    <button class="voxel-helpful-btn yes-btn" data-vote="yes">
                        <# if (settings.show_icon === 'yes') { #>
                            <span class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M22.773,7.721A4.994,4.994,0,0,0,19,6H15.011l.336-2.041A3.037,3.037,0,0,0,9.626,2.122L7.712,6H5a5.006,5.006,0,0,0-5,5v5a5.006,5.006,0,0,0,5,5H18.3a5.024,5.024,0,0,0,4.951-4.3l.705-5A5,5,0,0,0,22.773,7.721ZM2,16V11A3,3,0,0,1,5,8H7V19H5A3,3,0,0,1,2,16Zm19.971-4.581-.706,5A3.012,3.012,0,0,1,18.3,19H9V7.734a1,1,0,0,0,.23-.292l2.189-4.435A1.07,1.07,0,0,1,13.141,2.8a1.024,1.024,0,0,1,.233.84l-.528,3.2A1,1,0,0,0,13.833,8H19a3,3,0,0,1,2.971,3.419Z"/>
                                </svg>
                            </span>
                        <# } #>
                        <span class="btn-text">{{{ settings.yes_button_text }}}</span>
                    </button>

                    <button class="voxel-helpful-btn no-btn" data-vote="no">
                        <# if (settings.show_icon === 'yes') { #>
                            <span class="btn-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                                    <path d="M23.951,12.3l-.705-5A5.024,5.024,0,0,0,18.3,3H5A5.006,5.006,0,0,0,0,8v5a5.006,5.006,0,0,0,5,5H7.712l1.914,3.878a3.037,3.037,0,0,0,5.721-1.837L15.011,18H19a5,5,0,0,0,4.951-5.7ZM5,5H7V16H5a3,3,0,0,1-3-3V8A3,3,0,0,1,5,5Zm16.264,9.968A3,3,0,0,1,19,16H13.833a1,1,0,0,0-.987,1.162l.528,3.2a1.024,1.024,0,0,1-.233.84,1.07,1.07,0,0,1-1.722-.212L9.23,16.558A1,1,0,0,0,9,16.266V5h9.3a3.012,3.012,0,0,1,2.97,2.581l.706,5A3,3,0,0,1,21.264,14.968Z"/>
                                </svg>
                            </span>
                        <# } #>
                        <span class="btn-text">{{{ settings.no_button_text }}}</span>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }
}
