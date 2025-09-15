<?php
/**
 * Previous/Next Navigation Widget for Voxel Toolkit
 * 
 * Elementor widget for navigating between posts in chronological order
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Prev_Next_Widget extends \Elementor\Widget_Base {

    public function get_name() { 
        return 'voxel_prev_next_navigation'; 
    }
    
    public function get_title() { 
        return __('Previous/Next Navigation (VT)', 'voxel-toolkit'); 
    }
    
    public function get_icon() { 
        return 'eicon-navigation-horizontal'; 
    }
    
    public function get_categories() { 
        return ['voxel-toolkit']; 
    }

    public function get_keywords() {
        return ['navigation', 'prev', 'next', 'post', 'voxel'];
    }

    protected function register_controls() {
        // Content: non-style logic only
        $this->start_controls_section('section_content', ['label' => __('Content', 'voxel-toolkit')]);

        $this->add_control('show_placeholders', [
            'label'   => __('Show Placeholders', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
            'description' => __('Show "No Previous" and "No Next" when there are no adjacent posts', 'voxel-toolkit'),
        ]);

        $this->end_controls_section();

        // Style: Layout and visibility (responsive)
        $this->start_controls_section('section_layout', [
            'label' => __('Layout', 'voxel-toolkit'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('layout_direction', [
            'label'   => __('Direction', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'row'    => ['title' => __('Row', 'voxel-toolkit'), 'icon' => 'eicon-h-align-stretch'],
                'column' => ['title' => __('Column', 'voxel-toolkit'), 'icon' => 'eicon-v-align-stretch'],
            ],
            'default' => 'row',
            'toggle'  => false,
            'selectors' => [
                '{{WRAPPER}} .voxel-prev-next-nav' => 'flex-direction: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('items_justify', [
            'label'   => __('Justify', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::SELECT,
            'options' => [
                'flex-start'    => __('Start', 'voxel-toolkit'),
                'center'        => __('Center', 'voxel-toolkit'),
                'space-between' => __('Space Between', 'voxel-toolkit'),
                'flex-end'      => __('End', 'voxel-toolkit'),
            ],
            'default' => 'space-between',
            'selectors' => [
                '{{WRAPPER}} .voxel-prev-next-nav' => 'justify-content: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('item_width', [
            'label'   => __('Item Width', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'auto' => ['title' => __('Auto', 'voxel-toolkit'), 'icon' => 'eicon-width'],
                'full' => ['title' => __('Full', 'voxel-toolkit'), 'icon' => 'eicon-h-align-stretch'],
            ],
            'default' => 'auto',
            'toggle'  => false,
            'selectors_dictionary' => [
                'auto' => 'auto',
                'full' => '100%',
            ],
            'selectors' => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-item' => 'width: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('spacing', [
            'label'      => __('Spacing between elements', 'voxel-toolkit'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em'],
            'range'      => ['px' => ['min' => 0, 'max' => 50]],
            'default'    => ['size' => 8, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-item' => 'gap: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_visibility', [
            'label'   => __('Icon', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'show' => ['title' => __('Show', 'voxel-toolkit'), 'icon' => 'eicon-check'],
                'hide' => ['title' => __('Hide', 'voxel-toolkit'), 'icon' => 'eicon-ban'],
            ],
            'default' => 'show',
            'toggle'  => false,
            'selectors_dictionary' => [
                'show' => 'inline-flex',
                'hide' => 'none',
            ],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-icon' => 'display: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('text_visibility', [
            'label'   => __('Title Text', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'show' => ['title' => __('Show', 'voxel-toolkit'), 'icon' => 'eicon-check'],
                'hide' => ['title' => __('Hide', 'voxel-toolkit'), 'icon' => 'eicon-ban'],
            ],
            'default' => 'show',
            'toggle'  => false,
            'selectors_dictionary' => [
                'show' => 'inline-block',
                'hide' => 'none',
            ],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-link' => 'display: {{VALUE}};',
            ],
        ]);

        $this->add_responsive_control('thumb_visibility', [
            'label'   => __('Thumbnail', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::CHOOSE,
            'options' => [
                'show' => ['title' => __('Show', 'voxel-toolkit'), 'icon' => 'eicon-check'],
                'hide' => ['title' => __('Hide', 'voxel-toolkit'), 'icon' => 'eicon-ban'],
            ],
            'default' => 'show',
            'toggle'  => false,
            'selectors_dictionary' => [
                'show' => 'inline-flex',
                'hide' => 'none',
            ],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-thumb' => 'display: {{VALUE}};',
            ],
        ]);

        $this->end_controls_section();

        // Style: Element sizes
        $this->start_controls_section('section_elements', [
            'label' => __('Elements & Sizes', 'voxel-toolkit'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_responsive_control('thumb_size', [
            'label'      => __('Thumbnail Size', 'voxel-toolkit'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', '%', 'em'],
            'range'      => ['px' => ['min' => 10, 'max' => 300]],
            'default'    => ['size' => 60, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-thumb'     => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                '{{WRAPPER}} .voxel-prev-next-nav .nav-thumb img' => 'width: 100%; height: 100%; object-fit: cover;',
            ],
        ]);

        $this->add_responsive_control('thumb_radius', [
            'label'      => __('Thumbnail Border Radius', 'voxel-toolkit'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', '%'],
            'range'      => ['px' => ['min' => 0, 'max' => 100]],
            'default'    => ['size' => 50, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-thumb, {{WRAPPER}} .voxel-prev-next-nav .nav-thumb img' => 'border-radius: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->add_responsive_control('icon_size', [
            'label'      => __('Icon Size', 'voxel-toolkit'),
            'type'       => \Elementor\Controls_Manager::SLIDER,
            'size_units' => ['px', 'em', '%'],
            'range'      => ['px' => ['min' => 8, 'max' => 100]],
            'default'    => ['size' => 24, 'unit' => 'px'],
            'selectors'  => [
                '{{WRAPPER}} .voxel-prev-next-nav .nav-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
            ],
        ]);

        $this->end_controls_section();

        // Style: Typography & Colors
        $this->start_controls_section('section_style', [
            'label' => __('Typography & Colors', 'voxel-toolkit'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('link_color', [
            'label'     => __('Link Color', 'voxel-toolkit'),
            'type'      => \Elementor\Controls_Manager::COLOR,
            'selectors' => ['{{WRAPPER}} .voxel-prev-next-nav .nav-link' => 'color: {{VALUE}};'],
        ]);

        $this->add_group_control(\Elementor\Group_Control_Typography::get_type(), [
            'name'     => 'link_typography',
            'label'    => __('Link Typography', 'voxel-toolkit'),
            'selector' => '{{WRAPPER}} .voxel-prev-next-nav .nav-link',
        ]);

        $this->end_controls_section();

        // Style: Hover
        $this->start_controls_section('section_hover', [
            'label' => __('Hover Effects', 'voxel-toolkit'),
            'tab'   => \Elementor\Controls_Manager::TAB_STYLE,
        ]);

        $this->add_control('thumb_hover_zoom', [
            'label'   => __('Thumbnail Zoom', 'voxel-toolkit'),
            'type'    => \Elementor\Controls_Manager::SWITCHER,
            'default' => 'yes',
        ]);

        $this->end_controls_section();
    }

    protected function render() {
        global $post;
        if (!$post || !is_singular()) { 
            return; 
        }
        
        $post_type = get_post_type($post);
        if ($post_type === 'page') { 
            return; 
        }

        $settings      = $this->get_settings_for_display();
        $previous_post = $this->get_adjacent_by_date($post->ID, $post_type, true);
        $next_post     = $this->get_adjacent_by_date($post->ID, $post_type, false);
        $uid           = 'voxel-prev-next-' . esc_attr($this->get_id());

        echo '<div id="' . esc_attr($uid) . '" class="voxel-prev-next-nav" role="navigation" aria-label="' . esc_attr__('Post navigation', 'voxel-toolkit') . '">';

        // Previous Post
        echo '<div class="nav-item nav-prev">';
        if ($previous_post) {
            $prev_url   = esc_url(get_permalink($previous_post->ID));
            $prev_title = esc_html(get_the_title($previous_post->ID));

            // Icon (always render, responsive CSS controls visibility)
            echo '<a href="' . $prev_url . '" class="nav-icon" rel="prev" aria-label="' . esc_attr(sprintf(__('Previous: %s', 'voxel-toolkit'), $prev_title)) . '">';
            echo '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M15.41 7.41L14 6l-6 6 6 6 1.41-1.41L10.83 12z"/></svg>';
            echo '</a>';

            // Text (always render)
            echo '<a class="nav-link" href="' . $prev_url . '" rel="prev">' . $prev_title . '</a>';

            // Thumbnail (render only if exists; visibility per responsive control)
            if (has_post_thumbnail($previous_post->ID)) {
                echo '<a href="' . $prev_url . '" class="nav-thumb" rel="prev" aria-label="' . esc_attr(sprintf(__('Previous: %s', 'voxel-toolkit'), $prev_title)) . '">';
                echo get_the_post_thumbnail($previous_post->ID, 'thumbnail', ['loading' => 'lazy']);
                echo '</a>';
            }
        } elseif (!empty($settings['show_placeholders'])) {
            echo '<span class="nav-placeholder">&laquo; ' . esc_html__('No Previous', 'voxel-toolkit') . '</span>';
        }
        echo '</div>';

        // Next Post
        echo '<div class="nav-item nav-next">';
        if ($next_post) {
            $next_url   = esc_url(get_permalink($next_post->ID));
            $next_title = esc_html(get_the_title($next_post->ID));

            // Thumbnail (render only if exists; visibility per responsive control)
            if (has_post_thumbnail($next_post->ID)) {
                echo '<a href="' . $next_url . '" class="nav-thumb" rel="next" aria-label="' . esc_attr(sprintf(__('Next: %s', 'voxel-toolkit'), $next_title)) . '">';
                echo get_the_post_thumbnail($next_post->ID, 'thumbnail', ['loading' => 'lazy']);
                echo '</a>';
            }

            // Text (always render)
            echo '<a class="nav-link" href="' . $next_url . '" rel="next">' . $next_title . '</a>';

            // Icon (always render, responsive CSS controls visibility)
            echo '<a href="' . $next_url . '" class="nav-icon" rel="next" aria-label="' . esc_attr(sprintf(__('Next: %s', 'voxel-toolkit'), $next_title)) . '">';
            echo '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6z"/></svg>';
            echo '</a>';
        } elseif (!empty($settings['show_placeholders'])) {
            echo '<span class="nav-placeholder">' . esc_html__('No Next', 'voxel-toolkit') . ' &raquo;</span>';
        }
        echo '</div>';

        echo '</div>';

        ?>
        <style>
            /* Minimal defaults only; no properties that responsive controls manage */
            #<?php echo esc_attr($uid); ?> {
                display: flex;
                align-items: center;
            }
            #<?php echo esc_attr($uid); ?> .nav-item {
                display: flex;
                align-items: center;
            }
            #<?php echo esc_attr($uid); ?> .nav-thumb img { display: block; }
            #<?php echo esc_attr($uid); ?> .nav-link { text-decoration: none; transition: color 0.3s ease; }
            #<?php echo esc_attr($uid); ?> .nav-icon svg { display: block; transition: transform 0.3s ease; }
            #<?php echo esc_attr($uid); ?> .nav-prev .nav-icon:hover svg { transform: translateX(-2px); }
            #<?php echo esc_attr($uid); ?> .nav-next .nav-icon:hover svg { transform: translateX(2px); }
            <?php if (!empty($settings['thumb_hover_zoom'])) : ?>
            #<?php echo esc_attr($uid); ?> .nav-thumb img { transition: transform 0.3s ease; }
            #<?php echo esc_attr($uid); ?> .nav-thumb:hover img { transform: scale(1.06); }
            <?php endif; ?>
        </style>
        <?php
    }

    protected function get_adjacent_by_date($current_id, $post_type, $previous = true) {
        global $wpdb;

        $current_date = get_post_field('post_date', $current_id);
        if (!$current_date) { 
            return null; 
        }

        $op    = $previous ? '<' : '>';
        $order = $previous ? 'DESC' : 'ASC';
        $id_op = $previous ? '<' : '>';

        $status_in = "'publish'";

        $sql = "
            SELECT p.ID
            FROM {$wpdb->posts} p
            WHERE p.post_type = %s
              AND p.post_status IN ($status_in)
              AND (
                    p.post_date $op %s
                    OR (p.post_date = %s AND p.ID $id_op %d)
                  )
            ORDER BY p.post_date $order, p.ID $order
            LIMIT 1
        ";

        $id = $wpdb->get_var($wpdb->prepare($sql, $post_type, $current_date, $current_date, $current_id));
        return $id ? get_post((int) $id) : null;
    }
}