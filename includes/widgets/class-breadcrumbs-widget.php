<?php
/**
 * Breadcrumbs Widget
 *
 * Hierarchical navigation breadcrumbs with schema markup and customizable styling
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Breadcrumbs_Widget extends \Elementor\Widget_Base {

    /**
     * Constructor
     */
    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        wp_register_style(
            'voxel-breadcrumbs',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/breadcrumbs.css',
            [],
            VOXEL_TOOLKIT_VERSION
        );
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['voxel-breadcrumbs'];
    }

    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-breadcrumbs';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Breadcrumbs (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-navigation-horizontal';
    }

    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit', 'general'];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['breadcrumbs', 'navigation', 'trail', 'path', 'voxel', 'breadcrumb'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        $this->register_content_controls();
        $this->register_style_controls();
    }

    /**
     * Register content tab controls
     */
    private function register_content_controls() {
        // General Settings Section
        $this->start_controls_section(
            'general_section',
            [
                'label' => __('General Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'home_text',
            [
                'label' => __('Home Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Home', 'voxel-toolkit'),
                'placeholder' => __('Home', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_home',
            [
                'label' => __('Show Home Link', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'separator_type',
            [
                'label' => __('Separator', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'slash',
                'options' => [
                    'slash' => '/',
                    'greater' => '>',
                    'arrow' => '→',
                    'pipe' => '|',
                    'dot' => '·',
                    'custom' => __('Custom', 'voxel-toolkit'),
                ],
            ]
        );

        $this->add_control(
            'separator_custom',
            [
                'label' => __('Custom Separator', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '/',
                'placeholder' => '/',
                'condition' => [
                    'separator_type' => 'custom',
                ],
            ]
        );

        $this->add_control(
            'show_current',
            [
                'label' => __('Show Current Page', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'max_depth',
            [
                'label' => __('Max Depth', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 10,
                'description' => __('Maximum number of breadcrumb levels to display', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Structure Options Section
        $this->start_controls_section(
            'structure_section',
            [
                'label' => __('Structure Options', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'include_archive',
            [
                'label' => __('Include Post Type Archive', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Show archive link before single posts', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_parents',
            [
                'label' => __('Show Parent Posts', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Display hierarchical parent chain', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'prefix_text',
            [
                'label' => __('Prefix Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => __('You are here:', 'voxel-toolkit'),
                'description' => __('Optional text before breadcrumbs', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'suffix_text',
            [
                'label' => __('Suffix Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => '',
                'description' => __('Optional text after breadcrumbs', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // SEO & Schema Section
        $this->start_controls_section(
            'seo_section',
            [
                'label' => __('SEO & Schema', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'enable_schema',
            [
                'label' => __('Enable Schema Markup', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => __('Add JSON-LD structured data for search engines', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'nofollow_links',
            [
                'label' => __('Add Nofollow to Links', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => '',
                'description' => __('Add rel="nofollow" to all breadcrumb links', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style tab controls
     */
    private function register_style_controls() {
        // Link Styling Section
        $this->start_controls_section(
            'link_style_section',
            [
                'label' => __('Link Styling', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'link_color',
            [
                'label' => __('Link Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#0073aa',
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-link' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'link_hover_color',
            [
                'label' => __('Link Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#005177',
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-link:hover' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'link_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .breadcrumb-link',
            ]
        );

        $this->add_control(
            'link_decoration',
            [
                'label' => __('Text Decoration', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'none',
                'options' => [
                    'none' => __('None', 'voxel-toolkit'),
                    'underline' => __('Underline', 'voxel-toolkit'),
                    'overline' => __('Overline', 'voxel-toolkit'),
                    'line-through' => __('Line Through', 'voxel-toolkit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-link' => 'text-decoration: {{VALUE}};',
                ],
            ]
        );

        $this->add_control(
            'link_hover_decoration',
            [
                'label' => __('Hover Text Decoration', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'underline',
                'options' => [
                    'none' => __('None', 'voxel-toolkit'),
                    'underline' => __('Underline', 'voxel-toolkit'),
                    'overline' => __('Overline', 'voxel-toolkit'),
                    'line-through' => __('Line Through', 'voxel-toolkit'),
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-link:hover' => 'text-decoration: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Current Page Styling Section
        $this->start_controls_section(
            'current_style_section',
            [
                'label' => __('Current Page', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'current_color',
            [
                'label' => __('Current Page Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-current' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'current_typography',
                'label' => __('Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .breadcrumb-current',
            ]
        );

        $this->end_controls_section();

        // Separator Styling Section
        $this->start_controls_section(
            'separator_style_section',
            [
                'label' => __('Separator', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'separator_color',
            [
                'label' => __('Separator Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#999999',
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-separator' => 'color: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'separator_size',
            [
                'label' => __('Separator Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 8,
                        'max' => 32,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0.5,
                        'max' => 2,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 14,
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-separator' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'separator_spacing',
            [
                'label' => __('Separator Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 3,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-separator' => 'margin-left: {{SIZE}}{{UNIT}}; margin-right: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'separator_opacity',
            [
                'label' => __('Separator Opacity', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 1,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-separator' => 'opacity: {{SIZE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Spacing & Layout Section
        $this->start_controls_section(
            'spacing_section',
            [
                'label' => __('Spacing & Layout', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_responsive_control(
            'item_gap',
            [
                'label' => __('Item Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 3,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 0,
                ],
                'selectors' => [
                    '{{WRAPPER}} .breadcrumb-list' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'alignment',
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
                    '{{WRAPPER}} .vt-breadcrumbs' => 'justify-content: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vertical_alignment',
            [
                'label' => __('Vertical Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::CHOOSE,
                'options' => [
                    'flex-start' => [
                        'title' => __('Top', 'voxel-toolkit'),
                        'icon' => 'eicon-v-align-top',
                    ],
                    'center' => [
                        'title' => __('Middle', 'voxel-toolkit'),
                        'icon' => 'eicon-v-align-middle',
                    ],
                    'flex-end' => [
                        'title' => __('Bottom', 'voxel-toolkit'),
                        'icon' => 'eicon-v-align-bottom',
                    ],
                ],
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .vt-breadcrumbs' => 'align-items: {{VALUE}};',
                    '{{WRAPPER}} .breadcrumb-list' => 'align-items: {{VALUE}};',
                ],
            ]
        );

        $this->end_controls_section();

        // Container Styling Section
        $this->start_controls_section(
            'container_style_section',
            [
                'label' => __('Container', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .vt-breadcrumbs',
            ]
        );

        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-breadcrumbs' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'container_margin',
            [
                'label' => __('Margin', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', 'em', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-breadcrumbs' => 'margin: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'label' => __('Border', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-breadcrumbs',
            ]
        );

        $this->add_responsive_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-breadcrumbs' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'container_box_shadow',
                'label' => __('Box Shadow', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-breadcrumbs',
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Get breadcrumbs array
     *
     * @return array Array of breadcrumb items
     */
    private function get_breadcrumbs() {
        $settings = $this->get_settings_for_display();
        $breadcrumbs = [];
        $max_depth = !empty($settings['max_depth']) ? absint($settings['max_depth']) : 10;

        // Add home if enabled
        if ($settings['show_home'] === 'yes') {
            $breadcrumbs[] = [
                'title' => !empty($settings['home_text']) ? $settings['home_text'] : __('Home', 'voxel-toolkit'),
                'url' => home_url('/'),
                'is_current' => is_front_page(),
            ];
        }

        // Don't add more breadcrumbs on homepage
        if (is_front_page()) {
            return $breadcrumbs;
        }

        // Handle different content types
        if (is_singular()) {
            $breadcrumbs = array_merge($breadcrumbs, $this->get_singular_breadcrumbs($settings));
        } elseif (is_archive()) {
            $breadcrumbs = array_merge($breadcrumbs, $this->get_archive_breadcrumbs($settings));
        } elseif (is_search()) {
            $breadcrumbs[] = [
                'title' => __('Search Results', 'voxel-toolkit'),
                'url' => '',
                'is_current' => true,
            ];
        } elseif (is_404()) {
            $breadcrumbs[] = [
                'title' => __('404 Not Found', 'voxel-toolkit'),
                'url' => '',
                'is_current' => true,
            ];
        }

        // Limit to max depth
        if (count($breadcrumbs) > $max_depth) {
            $breadcrumbs = array_slice($breadcrumbs, 0, $max_depth);
        }

        return $breadcrumbs;
    }

    /**
     * Get breadcrumbs for singular posts/pages
     *
     * @param array $settings Widget settings
     * @return array Breadcrumb items
     */
    private function get_singular_breadcrumbs($settings) {
        $breadcrumbs = [];
        $post = get_post();

        if (!$post) {
            return $breadcrumbs;
        }

        $post_type = get_post_type();
        $post_type_object = get_post_type_object($post_type);

        // Add post type archive link if enabled and available
        if ($settings['include_archive'] === 'yes' && $post_type_object && $post_type_object->has_archive && $post_type !== 'page') {
            $breadcrumbs[] = [
                'title' => $post_type_object->labels->name,
                'url' => get_post_type_archive_link($post_type),
                'is_current' => false,
            ];
        }

        // Add parent posts/pages if hierarchical
        if ($settings['show_parents'] === 'yes' && is_post_type_hierarchical($post_type)) {
            $parent_breadcrumbs = $this->get_parent_breadcrumbs($post->ID);
            $breadcrumbs = array_merge($breadcrumbs, $parent_breadcrumbs);
        }

        // Add primary taxonomy term for non-hierarchical post types
        if (!is_post_type_hierarchical($post_type) && $post_type === 'post') {
            $category = get_the_category($post->ID);
            if (!empty($category)) {
                $category = $category[0];
                // Add parent categories
                if ($category->parent) {
                    $parent_cats = get_category_parents($category->parent, true, '|||');
                    $parent_cats = explode('|||', $parent_cats);
                    array_pop($parent_cats); // Remove empty last element
                    foreach ($parent_cats as $parent_cat) {
                        if (preg_match('/<a[^>]+href="([^"]+)"[^>]*>([^<]+)<\/a>/', $parent_cat, $matches)) {
                            $breadcrumbs[] = [
                                'title' => $matches[2],
                                'url' => $matches[1],
                                'is_current' => false,
                            ];
                        }
                    }
                }
                // Add current category
                $breadcrumbs[] = [
                    'title' => $category->name,
                    'url' => get_category_link($category->term_id),
                    'is_current' => false,
                ];
            }
        }

        // Add current post/page if enabled
        if ($settings['show_current'] === 'yes') {
            $breadcrumbs[] = [
                'title' => get_the_title($post->ID),
                'url' => '',
                'is_current' => true,
            ];
        }

        return $breadcrumbs;
    }

    /**
     * Get breadcrumbs for archive pages
     *
     * @param array $settings Widget settings
     * @return array Breadcrumb items
     */
    private function get_archive_breadcrumbs($settings) {
        $breadcrumbs = [];

        if (is_category() || is_tag() || is_tax()) {
            $term = get_queried_object();

            if ($term && !is_wp_error($term)) {
                // Add parent terms
                if ($term->parent) {
                    $parent_terms = [];
                    $parent_id = $term->parent;

                    while ($parent_id) {
                        $parent_term = get_term($parent_id, $term->taxonomy);
                        if ($parent_term && !is_wp_error($parent_term)) {
                            array_unshift($parent_terms, [
                                'title' => $parent_term->name,
                                'url' => get_term_link($parent_term),
                                'is_current' => false,
                            ]);
                            $parent_id = $parent_term->parent;
                        } else {
                            break;
                        }
                    }

                    $breadcrumbs = array_merge($breadcrumbs, $parent_terms);
                }

                // Add current term
                if ($settings['show_current'] === 'yes') {
                    $breadcrumbs[] = [
                        'title' => $term->name,
                        'url' => '',
                        'is_current' => true,
                    ];
                }
            }
        } elseif (is_post_type_archive()) {
            $post_type = get_query_var('post_type');
            if (is_array($post_type)) {
                $post_type = reset($post_type);
            }
            $post_type_object = get_post_type_object($post_type);

            if ($post_type_object && $settings['show_current'] === 'yes') {
                $breadcrumbs[] = [
                    'title' => $post_type_object->labels->name,
                    'url' => '',
                    'is_current' => true,
                ];
            }
        } elseif (is_author()) {
            if ($settings['show_current'] === 'yes') {
                $breadcrumbs[] = [
                    'title' => __('Author: ', 'voxel-toolkit') . get_the_author(),
                    'url' => '',
                    'is_current' => true,
                ];
            }
        } elseif (is_date()) {
            if (is_day()) {
                $breadcrumbs[] = [
                    'title' => get_the_time('F'),
                    'url' => get_month_link(get_the_time('Y'), get_the_time('m')),
                    'is_current' => false,
                ];
                if ($settings['show_current'] === 'yes') {
                    $breadcrumbs[] = [
                        'title' => get_the_time('d'),
                        'url' => '',
                        'is_current' => true,
                    ];
                }
            } elseif (is_month()) {
                if ($settings['show_current'] === 'yes') {
                    $breadcrumbs[] = [
                        'title' => get_the_time('F Y'),
                        'url' => '',
                        'is_current' => true,
                    ];
                }
            } elseif (is_year()) {
                if ($settings['show_current'] === 'yes') {
                    $breadcrumbs[] = [
                        'title' => get_the_time('Y'),
                        'url' => '',
                        'is_current' => true,
                    ];
                }
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get parent breadcrumbs for hierarchical posts
     *
     * @param int $post_id Post ID
     * @return array Parent breadcrumb items
     */
    private function get_parent_breadcrumbs($post_id) {
        $breadcrumbs = [];
        $ancestors = get_post_ancestors($post_id);

        if (!empty($ancestors)) {
            $ancestors = array_reverse($ancestors);

            foreach ($ancestors as $ancestor_id) {
                $breadcrumbs[] = [
                    'title' => get_the_title($ancestor_id),
                    'url' => get_permalink($ancestor_id),
                    'is_current' => false,
                ];
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get separator based on settings
     *
     * @param array $settings Widget settings
     * @return string Separator HTML
     */
    private function get_separator($settings) {
        $separator = '';

        switch ($settings['separator_type']) {
            case 'slash':
                $separator = '/';
                break;
            case 'greater':
                $separator = '&gt;';
                break;
            case 'arrow':
                $separator = '&rarr;';
                break;
            case 'pipe':
                $separator = '|';
                break;
            case 'dot':
                $separator = '&middot;';
                break;
            case 'custom':
                $separator = !empty($settings['separator_custom']) ? esc_html($settings['separator_custom']) : '/';
                break;
        }

        return $separator;
    }

    /**
     * Render schema markup (JSON-LD)
     *
     * @param array $breadcrumbs Breadcrumb items
     */
    private function render_schema_markup($breadcrumbs) {
        $schema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [],
        ];

        $position = 1;
        foreach ($breadcrumbs as $crumb) {
            // Only add items with URLs to schema
            if (!empty($crumb['url'])) {
                $schema['itemListElement'][] = [
                    '@type' => 'ListItem',
                    'position' => $position,
                    'name' => wp_strip_all_tags($crumb['title']),
                    'item' => $crumb['url'],
                ];
                $position++;
            }
        }

        // Only output schema if there are items
        if (!empty($schema['itemListElement'])) {
            echo '<script type="application/ld+json">';
            echo wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            echo '</script>';
        }
    }

    /**
     * Render the widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        // Generate breadcrumbs
        $breadcrumbs = $this->get_breadcrumbs();

        // Return early if no breadcrumbs
        if (empty($breadcrumbs)) {
            return;
        }

        // Render schema markup if enabled
        if ($settings['enable_schema'] === 'yes') {
            $this->render_schema_markup($breadcrumbs);
        }

        // Get separator
        $separator = $this->get_separator($settings);

        // Build rel attribute
        $rel = $settings['nofollow_links'] === 'yes' ? ' rel="nofollow"' : '';

        ?>
        <nav class="vt-breadcrumbs" aria-label="<?php echo esc_attr__('Breadcrumb', 'voxel-toolkit'); ?>">
            <?php if (!empty($settings['prefix_text'])): ?>
                <span class="breadcrumb-prefix"><?php echo esc_html($settings['prefix_text']); ?></span>
            <?php endif; ?>

            <ol class="breadcrumb-list">
                <?php
                $total = count($breadcrumbs);
                foreach ($breadcrumbs as $index => $crumb):
                    $is_last = ($index === $total - 1);
                    ?>
                    <li class="breadcrumb-item <?php echo $crumb['is_current'] ? 'breadcrumb-current' : ''; ?>">
                        <?php if (!empty($crumb['url']) && !$crumb['is_current']): ?>
                            <a href="<?php echo esc_url($crumb['url']); ?>" class="breadcrumb-link"<?php echo $rel; ?>>
                                <?php echo esc_html($crumb['title']); ?>
                            </a>
                        <?php else: ?>
                            <span class="breadcrumb-current"<?php echo $crumb['is_current'] ? ' aria-current="page"' : ''; ?>>
                                <?php echo esc_html($crumb['title']); ?>
                            </span>
                        <?php endif; ?>
                    </li>

                    <?php if (!$is_last): ?>
                        <li class="breadcrumb-separator" aria-hidden="true"><?php echo $separator; ?></li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>

            <?php if (!empty($settings['suffix_text'])): ?>
                <span class="breadcrumb-suffix"><?php echo esc_html($settings['suffix_text']); ?></span>
            <?php endif; ?>
        </nav>
        <?php
    }
}
