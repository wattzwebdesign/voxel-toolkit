<?php
/**
 * Review Collection Widget for Elementor
 * 
 * Displays a collection of reviews from Voxel users
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Review_Collection_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel_toolkit_review_collection';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Review Collection (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-review';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel', 'voxel-toolkit'];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['reviews', 'rating', 'testimonial', 'voxel', 'collection'];
    }

    /**
     * Register widget controls
     */
    protected function _register_controls() {
        // CONTENT Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'number_of_reviews',
            [
                'label' => __('Number of reviews to display', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 3,
                'min' => 1,
                'max' => 20,
                'step' => 1,
            ]
        );

        $this->add_control(
            'minimum_rating',
            [
                'label' => __('Minimum rating', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 5,
                'step' => 1,
                'description' => __('Only show reviews with this rating or higher', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'post_id',
            [
                'label' => __('Post ID', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'description' => __('Enter the ID of the post to display reviews for. Leave empty for current post.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'user_id',
            [
                'label' => __('Author ID', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'description' => __('Enter the ID of the author to display reviews from. Leave empty for all authors.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'post_type_display',
            [
                'label' => __('Post Types', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'places,events,profile',
                'description' => __('Enter post type keys separated by commas. Leave empty for all types.', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_reviews_with_message',
            [
                'label' => __('Only show reviews with message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'default' => 'yes',
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'enable_link',
            [
                'label' => __('Enable link to post', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_responsive_control(
            'content_line_limit',
            [
                'label' => __('Content line limit', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 4,
                'min' => 1,
                'max' => 10,
                'description' => __('Number of lines to display for review content', 'voxel-toolkit'),
                'selectors' => [
                    '{{WRAPPER}} .review-content' => '-webkit-line-clamp: {{VALUE}}; line-clamp: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Date Section
        $this->start_controls_section(
            'date_section',
            [
                'label' => __('Date', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'date_prefix',
            [
                'label' => __('Date prefix', 'voxel-toolkit'),
                'default' => 'Published on',
                'type' => \Elementor\Controls_Manager::TEXT,
            ]
        );

        $this->add_control(
            'date_format',
            [
                'label' => __('Date format', 'voxel-toolkit'),
                'description' => __('PHP date format. Leave blank for WordPress default.', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'placeholder' => 'F j, Y',
            ]
        );

        $this->end_controls_section();

        // STYLE - General
        $this->start_controls_section(
            'general_style_section',
            [
                'label' => __('General', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'column_number',
            [
                'label' => __('Columns', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'step' => 1,
                'default' => 3,
                'selectors' => [
                    '{{WRAPPER}} .user-ratings-wrapper' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                ],
            ]
        );

        $this->add_responsive_control(
            'column_gap',
            [
                'label' => __('Gap between reviews', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .user-ratings-wrapper' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - REVIEW
        $this->start_controls_section(
            'review_style_section',
            [
                'label' => __('Review Card', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'review_background_color',
            [
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .user-rating' => 'background-color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'review_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default' => [
                    'unit' => 'px',
                    'top' => 20,
                    'right' => 20,
                    'bottom' => 20,
                    'left' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .user-rating' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'review_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .user-rating',
            ]
        );

        $this->add_responsive_control(
            'review_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => 'px',
                    'top' => 10,
                    'right' => 10,
                    'bottom' => 10,
                    'left' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .user-rating' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'review_box_shadow',
                'selector' => '{{WRAPPER}} .user-rating',
            ]
        );

        $this->end_controls_section();

        // TAB - IMAGE
        $this->start_controls_section(
            'image_style_section',
            [
                'label' => __('Post Image', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'image_size',
            [
                'label' => __('Image Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'default' => [
                    'unit' => 'px',
                    'size' => 60,
                ],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 200,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-thumbnail' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        if (function_exists('\Voxel\get_image_sizes_with_labels')) {
            $this->add_control(
                'img_post_thumbnail',
                [
                    'label' => __('Thumbnail Size', 'voxel-toolkit'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'default' => 'thumbnail',
                    'options' => \Voxel\get_image_sizes_with_labels(),
                ]
            );
        }

        $this->add_responsive_control(
            'image_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => '%',
                    'top' => 50,
                    'right' => 50,
                    'bottom' => 50,
                    'left' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-thumbnail' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'image_margin_bottom',
            [
                'label' => __('Bottom Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-thumbnail' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - TITLE
        $this->start_controls_section(
            'title_style_section',
            [
                'label' => __('Post Title', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'title_align',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .review-post-title' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'title_typography',
                'selector' => '{{WRAPPER}} .review-post-title',
            ]
        );

        $this->add_control(
            'title_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .review-post-title' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .review-post-title a' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'title_hover_color',
            [
                'label' => __('Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .review-post-title a:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'title_margin_bottom',
            [
                'label' => __('Bottom Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-post-title' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - RATING
        $this->start_controls_section(
            'rating_style_section',
            [
                'label' => __('Rating', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'rating_align',
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
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .review-rating' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'rating_icon',
            [
                'label' => __('Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-star',
                    'library' => 'fa-solid',
                ],
            ]
        );

        $this->add_responsive_control(
            'rating_icon_size',
            [
                'label' => __('Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 10,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 16,
                ],
                'selectors' => [
                    '{{WRAPPER}} .rating-icon' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .rating-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'rating_icon_color_active',
            [
                'label' => __('Active Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#FFB800',
                'selectors' => [
                    '{{WRAPPER}} .rating-icon-active' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .rating-icon-active svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'rating_icon_color_inactive',
            [
                'label' => __('Inactive Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#D4D6DD',
                'selectors' => [
                    '{{WRAPPER}} .rating-icon-inactive' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .rating-icon-inactive svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'rating_icon_spacing',
            [
                'label' => __('Icon Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 20,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 2,
                ],
                'selectors' => [
                    '{{WRAPPER}} .rating-icon:not(:last-child)' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'rating_margin_bottom',
            [
                'label' => __('Bottom Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-rating' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - AVATAR & USERNAME
        $this->start_controls_section(
            'avatar_style_section',
            [
                'label' => __('Author Section', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'author_section_align',
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
                'default' => 'flex-start',
                'selectors' => [
                    '{{WRAPPER}} .review-author' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'avatar_heading',
            [
                'label' => __('Avatar', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'avatar_size',
            [
                'label' => __('Avatar Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'default' => [
                    'unit' => 'px',
                    'size' => 30,
                ],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'avatar_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'unit' => '%',
                    'top' => 50,
                    'right' => 50,
                    'bottom' => 50,
                    'left' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'avatar_margin_right',
            [
                'label' => __('Right Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-user-avatar' => 'margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'username_heading',
            [
                'label' => __('Username', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'username_typography',
                'selector' => '{{WRAPPER}} .review-username',
            ]
        );

        $this->add_control(
            'username_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .review-username' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'author_section_margin_bottom',
            [
                'label' => __('Section Bottom Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-author' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - CONTENT
        $this->start_controls_section(
            'content_style_section',
            [
                'label' => __('Review Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'content_align',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                    'justify' => [
                        'title' => __('Justified', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-justify',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .review-content' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'content_typography',
                'selector' => '{{WRAPPER}} .review-content',
            ]
        );

        $this->add_control(
            'content_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .review-content' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'content_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'default' => [
                    'unit' => 'px',
                    'top' => 15,
                    'right' => 0,
                    'bottom' => 15,
                    'left' => 0,
                    'isLinked' => false,
                ],
                'selectors' => [
                    '{{WRAPPER}} .review-content' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();

        // TAB - DATE
        $this->start_controls_section(
            'date_style_section',
            [
                'label' => __('Date', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'date_align',
            [
                'label' => __('Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => __('Left', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'center' => [
                        'title' => __('Center', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-center',
                    ],
                    'right' => [
                        'title' => __('Right', 'voxel-toolkit'),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => 'left',
                'selectors' => [
                    '{{WRAPPER}} .review-created-at' => 'text-align: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'date_typography',
                'selector' => '{{WRAPPER}} .review-created-at',
            ]
        );

        $this->add_control(
            'date_text_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999999',
                'selectors' => [
                    '{{WRAPPER}} .review-created-at' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_id = !empty($settings['post_id']) ? $settings['post_id'] : '';
        $user_id = !empty($settings['user_id']) ? $settings['user_id'] : '';
        $post_type_display = !empty($settings['post_type_display']) ? explode(',', $settings['post_type_display']) : [];
        $user_ratings_info = $this->get_user_rating_info($post_id, $user_id, $post_type_display, $settings['date_format'], $settings['date_prefix']);
        $number_of_reviews = $settings['number_of_reviews'];
        $user_ratings_info = array_reverse($user_ratings_info);
        $widget_unique_class = 'user-reviews-widget-' . $this->get_id();
        echo '<div class="user-ratings-wrapper elementor-grid ' . esc_attr($widget_unique_class) . '">';
        $counter = 0;
        foreach ($user_ratings_info as $info) {
            if ($counter >= $number_of_reviews) {
                break;
            }
            if ($settings['show_reviews_with_message'] === 'yes' && empty($info['content'])) {
                continue;
            }
            $real_rating = floatval($info['review_score']) + 3;
            if ($real_rating >= $settings['minimum_rating']) {
                echo '<div class="user-rating ' . esc_attr($widget_unique_class . '-rating') . '" style="display: flex;flex-direction: column;justify-content: space-between;">';
                echo '<div class="review-user-rating">';
                if (!empty($info['image_url'])) {
                    $thumbnail_size = $settings['img_post_thumbnail'];
                    $image_url = wp_get_attachment_image_url($info['image_id'], $thumbnail_size);
                    if ($image_url) {
                        echo '<img src="' . esc_url($image_url) . '" class="review-user-thumbnail" alt="' . esc_attr($info['post_title']) . '">';
                    }
                }
                if (!empty($info['post_title'])) {
                    if ($settings['enable_link']) {
                        echo '<p class="review-post-title"><a href="' . $info['post_url'] . '">' . $info['post_title'] . '</a></p>';
                    } else {
                        echo '<p class="review-post-title">' . $info['post_title'] . '</p>';
                    }
                }
                echo '<div class="review-rating" style="display: flex;">';
                for ($i = 0; $i < 5; $i++) {
                    if ($i < $real_rating) {
                        echo '<div class="rating-icon rating-icon-active">';
                        echo \Voxel\get_icon_markup($settings['rating_icon']);
                        echo '</div>';
                    } else {
                        echo '<div class="rating-icon rating-icon-inactive">';
                        echo \Voxel\get_icon_markup($settings['rating_icon']);
                        echo '</div>';
                    }
                }
                echo '</div>';
                echo '<div class="review-author" style="display: flex;align-items: center;">';
                if (!empty($info['user_avatar_url'])) {
                    echo '<img src="' . esc_url($info['user_avatar_url']) . '" class="review-user-avatar" alt="' . esc_attr($info['username']) . '">';
                }
                echo '<p class="review-username">' . $info['username'] . '</p>';
                echo '</div>';
                echo '<p class="review-content" style="overflow: hidden;text-overflow: ellipsis;display: -webkit-box;-webkit-box-orient: vertical;">' . $info['content'] . '</p></div>';
                echo '<div class="review-created-at">' . $info['created_at_with_prefix'] . '</div>';
                echo '</div>';
                $counter++;
            }
        }
        echo '</div>';
    }


    private function get_user_rating_info($post_id, $user_id, $allowed_post_types, $date_format, $date_prefix) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'voxel_timeline';
        $query = "SELECT user_id, content, review_score, details, post_id, created_at FROM $table_name WHERE details LIKE '%\"rating\":%'";
        if (!empty($post_id)) {
            $query .= " AND post_id = $post_id";
        }
        if (!empty($user_id)) {
            $query .= " AND user_id = $user_id";
        }
        $results = $wpdb->get_results($query);
        $user_ratings_info = [];
        foreach ($results as $result) {
            $user_id = $result->user_id;
            $content = $result->content;
            $review_score = floatval($result->review_score);
            $post_id = $result->post_id;
            $timestamp = strtotime($result->created_at);
            $local_timestamp = strtotime(current_time('mysql', 1));
            $date_format = ! empty($date_format) ? $date_format : '';
            if (empty($date_format)) {
                $date_format = get_option('date_format');
            }
            $created_at = date_i18n($date_format, $timestamp + ($local_timestamp - time()));
            $created_at_with_prefix = $date_prefix . ' ' . $created_at;
            $post_type = get_post_field('post_type', $post_id);
            if ($post_type === 'profile') {
            $post_author_id = get_post_field('post_author', $post_id);
            $author_name = get_the_author_meta('display_name', $post_author_id);
            $post_title = $author_name;
            } else {
                $post_title = get_the_title($post_id);
            }
            if (empty($allowed_post_types) || in_array($post_type, $allowed_post_types)) {
                preg_match('/"score":(-?\d+)/', $result->details, $matches);
                if (!empty($matches)) {
                    $rating = intval($matches[1]) + 3;
                    $user_info = get_userdata($user_id);
                    $user_avatar_url = get_avatar_url($user_id);
                    $username = $user_info->display_name;
                    $image_id = get_post_thumbnail_id($post_id);
                    $image_url = wp_get_attachment_url($image_id);
                    $user_ratings_info[] = [
                        'post_id' => $post_id,
                        'user_id' => $user_id,
                        'user_avatar_url' => $user_avatar_url,
                        'username' => $username,
                        'content' => $content,
                        'review_score' => $review_score,
                        'image_id' => $image_id,
                        'image_url' => $image_url,
                        'post_title' => $post_title,
                        'post_url' => get_permalink($post_id),
                        'created_at' => $created_at,
                        'created_at_with_prefix' => $created_at_with_prefix
                    ];
                }
            }
        }
        return $user_ratings_info;
    }
}