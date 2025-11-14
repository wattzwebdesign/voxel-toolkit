<?php
/**
 * Timeline Photos Widget for Elementor
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Elementor_Timeline_Photos extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-timeline-photos';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Timeline Photos (VT)', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-gallery-grid';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit', 'general'];
    }
    
    /**
     * Get help URL
     */
    public function get_custom_help_url() {
        return 'https://codewattz.com/doc';
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['timeline', 'photos', 'gallery', 'reviews', 'voxel', 'masonry'];
    }
    
    /**
     * Register widget controls
     */
    protected function register_controls() {
        
        // Content Section - Gallery Settings
        $this->start_controls_section(
            'gallery_settings',
            [
                'label' => __('Gallery Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'post_id',
            [
                'label' => __('Post ID', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => '',
                'placeholder' => __('Leave empty to use current post', 'voxel-toolkit'),
                'description' => __('Specify post ID or leave empty to use current post', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'gallery_layout',
            [
                'label' => __('Gallery Layout', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'masonry',
                'options' => [
                    'masonry' => __('Masonry', 'voxel-toolkit'),
                    'grid' => __('Grid', 'voxel-toolkit'),
                    'justified' => __('Justified', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'aspect_ratio',
            [
                'label' => __('Aspect Ratio', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'auto',
                'options' => [
                    'auto' => __('Auto', 'voxel-toolkit'),
                    '1-1' => __('1:1 (Square)', 'voxel-toolkit'),
                    '4-3' => __('4:3', 'voxel-toolkit'),
                    '3-2' => __('3:2', 'voxel-toolkit'),
                    '16-9' => __('16:9', 'voxel-toolkit'),
                    '2-1' => __('2:1', 'voxel-toolkit'),
                ],
                'condition' => [
                    'gallery_layout' => 'grid',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'columns',
            [
                'label' => __('Columns', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 1,
                        'max' => 6,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 3,
                ],
                'tablet_default' => [
                    'unit' => 'px',
                    'size' => 2,
                ],
                'mobile_default' => [
                    'unit' => 'px',
                    'size' => 1,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-timeline-photos' => '--columns: {{SIZE}};',
                    '(tablet) {{WRAPPER}} .voxel-timeline-photos' => '--columns-tablet: {{columns_tablet.SIZE}};',
                    '(mobile) {{WRAPPER}} .voxel-timeline-photos' => '--columns-mobile: {{columns_mobile.SIZE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'gap',
            [
                'label' => __('Gap', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', 'em'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                    'em' => [
                        'min' => 0,
                        'max' => 5,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .voxel-timeline-photos' => 'gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'enable_lightbox',
            [
                'label' => __('Enable Lightbox', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'image_size',
            [
                'label' => __('Image Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'medium_large',
                'options' => [
                    'thumbnail' => __('Thumbnail', 'voxel-toolkit'),
                    'medium' => __('Medium', 'voxel-toolkit'),
                    'medium_large' => __('Medium Large', 'voxel-toolkit'),
                    'large' => __('Large', 'voxel-toolkit'),
                    'full' => __('Full Size', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'photo_limit',
            [
                'label' => __('Photo Limit', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __('Maximum number of photos to display (0 = unlimited)', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'photo_offset',
            [
                'label' => __('Photo Offset', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 0,
                'min' => 0,
                'description' => __('Number of photos to skip from the beginning (e.g., 2 will skip the first 2 photos)', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'show_empty_message',
            [
                'label' => __('Show Empty Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'empty_message',
            [
                'label' => __('Empty Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('No photos found in reviews', 'voxel-toolkit'),
                'condition' => [
                    'show_empty_message' => 'yes',
                ],
            ]
        );

        $this->end_controls_section();
        
        // Style Section - Gallery Style
        $this->start_controls_section(
            'gallery_style',
            [
                'label' => __('Gallery Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_responsive_control(
            'item_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'default' => [
                    'top' => 8,
                    'right' => 8,
                    'bottom' => 8,
                    'left' => 8,
                    'unit' => 'px',
                ],
                'selectors' => [
                    '{{WRAPPER}} .timeline-photo-item img' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'item_box_shadow',
                'selector' => '{{WRAPPER}} .timeline-photo-item',
            ]
        );
        
        $this->add_control(
            'hover_effect',
            [
                'label' => __('Hover Effect', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'zoom',
                'options' => [
                    'none' => __('None', 'voxel-toolkit'),
                    'zoom' => __('Zoom', 'voxel-toolkit'),
                    'opacity' => __('Opacity', 'voxel-toolkit'),
                    'scale' => __('Scale', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'hover_overlay_color',
            [
                'label' => __('Hover Overlay Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(0,0,0,0.3)',
                'selectors' => [
                    '{{WRAPPER}} .timeline-photo-item:hover .photo-overlay' => 'background-color: {{VALUE}};',
                ],
                'condition' => [
                    'hover_effect!' => 'none',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section - Empty Message
        $this->start_controls_section(
            'empty_message_style',
            [
                'label' => __('Empty Message', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
                'condition' => [
                    'show_empty_message' => 'yes',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'empty_message_typography',
                'selector' => '{{WRAPPER}} .timeline-photos-empty',
            ]
        );
        
        $this->add_control(
            'empty_message_color',
            [
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#666',
                'selectors' => [
                    '{{WRAPPER}} .timeline-photos-empty' => 'color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'empty_message_align',
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
                'default' => 'center',
                'selectors' => [
                    '{{WRAPPER}} .timeline-photos-empty' => 'text-align: {{VALUE}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Content Section - Debug Mode
        $this->start_controls_section(
            'debug_settings',
            [
                'label' => __('Debug Mode', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'debug_mode',
            [
                'label' => __('Enable Debug Mode', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Yes', 'voxel-toolkit'),
                'label_off' => __('No', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Show debug information about timeline data instead of photos', 'voxel-toolkit'),
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Get timeline photos from database
     */
    private function get_timeline_photos($post_id) {
        global $wpdb;
        
        if (!$post_id) {
            return [];
        }
        
        // Query the voxel timeline table for post_reviews
        $table_name = $wpdb->prefix . 'voxel_timeline';
        $query = $wpdb->prepare(
            "SELECT details FROM {$table_name} WHERE post_id = %d AND feed = 'post_reviews'",
            $post_id
        );
        
        $results = $wpdb->get_results($query);
        
        if (empty($results)) {
            return [];
        }
        
        $file_ids = [];
        
        foreach ($results as $result) {
            $details = json_decode($result->details, true);
            
            if (isset($details['files']) && !empty($details['files'])) {
                // Handle both single file ID and array of file IDs
                if (is_array($details['files'])) {
                    $file_ids = array_merge($file_ids, $details['files']);
                } else {
                    // Handle comma-separated string of IDs
                    if (is_string($details['files']) && strpos($details['files'], ',') !== false) {
                        $ids = explode(',', $details['files']);
                        $file_ids = array_merge($file_ids, array_map('trim', $ids));
                    } else {
                        $file_ids[] = $details['files'];
                    }
                }
            }
        }
        
        // Remove duplicates and filter out empty values
        $file_ids = array_filter(array_unique($file_ids));
        
        if (empty($file_ids)) {
            return [];
        }
        
        // Get attachment data for each file ID
        $photos = [];
        foreach ($file_ids as $file_id) {
            $attachment = get_post($file_id);
            if ($attachment && $attachment->post_type === 'attachment' && wp_attachment_is_image($file_id)) {
                $photos[] = [
                    'id' => $file_id,
                    'url' => wp_get_attachment_url($file_id),
                    'title' => get_the_title($file_id),
                    'alt' => get_post_meta($file_id, '_wp_attachment_image_alt', true),
                ];
            }
        }
        
        return $photos;
    }
    
    /**
     * Render the widget
     */
    protected function render() {
        global $post;
        
        $settings = $this->get_settings_for_display();
        $post_id = !empty($settings['post_id']) ? intval($settings['post_id']) : (isset($post->ID) ? $post->ID : 0);
        
        if (!$post_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="timeline-photos-empty">' . esc_html__('Please specify a post ID or view on a post page', 'voxel-toolkit') . '</div>';
            }
            return;
        }
        
        // If debug mode is enabled, show debug information
        if ($settings['debug_mode'] === 'yes') {
            if (class_exists('Voxel_Toolkit_Timeline_Photos_Widget')) {
                $debug_info = Voxel_Toolkit_Timeline_Photos_Widget::debug_timeline_data($post_id);
                echo '<div class="timeline-photos-debug"><pre>' . esc_html($debug_info) . '</pre></div>';
            } else {
                echo '<div class="timeline-photos-empty">Debug mode requires Voxel_Toolkit_Timeline_Photos_Widget class</div>';
            }
            return;
        }
        
        $photos = $this->get_timeline_photos($post_id);

        // Apply offset and limit
        $offset = isset($settings['photo_offset']) ? intval($settings['photo_offset']) : 0;
        $limit = isset($settings['photo_limit']) ? intval($settings['photo_limit']) : 0;

        // Apply offset
        if ($offset > 0 && !empty($photos)) {
            $photos = array_slice($photos, $offset);
        }

        // Apply limit
        if ($limit > 0 && !empty($photos)) {
            $photos = array_slice($photos, 0, $limit);
        }

        if (empty($photos)) {
            if ($settings['show_empty_message'] === 'yes') {
                echo '<div class="timeline-photos-empty">' . esc_html($settings['empty_message']) . '</div>';
            }
            return;
        }
        
        $gallery_classes = [
            'voxel-timeline-photos',
            'layout-' . $settings['gallery_layout'],
            'hover-' . $settings['hover_effect'],
        ];
        
        if ($settings['gallery_layout'] === 'grid' && $settings['aspect_ratio'] !== 'auto') {
            $gallery_classes[] = 'aspect-' . $settings['aspect_ratio'];
        }
        
        $lightbox_attr = $settings['enable_lightbox'] === 'yes' ? 'data-elementor-open-lightbox="yes"' : '';
        
        ?>
        <div class="<?php echo esc_attr(implode(' ', $gallery_classes)); ?>">
            <?php foreach ($photos as $photo): ?>
                <div class="timeline-photo-item">
                    <a href="<?php echo esc_url($photo['url']); ?>" 
                       <?php echo $lightbox_attr; ?>
                       data-elementor-lightbox-slideshow="timeline-photos-<?php echo esc_attr($this->get_id()); ?>"
                       title="<?php echo esc_attr($photo['title']); ?>">
                        <?php
                        echo wp_get_attachment_image(
                            $photo['id'], 
                            $settings['image_size'], 
                            false, 
                            [
                                'alt' => $photo['alt'],
                                'title' => $photo['title'],
                            ]
                        );
                        ?>
                        <?php if ($settings['hover_effect'] !== 'none'): ?>
                            <div class="photo-overlay"></div>
                        <?php endif; ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render widget output in the editor
     */
    protected function content_template() {
        ?>
        <#
        // Static preview for editor
        var samplePhotos = [
            { id: 1, title: 'Review Photo 1' },
            { id: 2, title: 'Review Photo 2' },
            { id: 3, title: 'Review Photo 3' },
            { id: 4, title: 'Review Photo 4' },
            { id: 5, title: 'Review Photo 5' },
            { id: 6, title: 'Review Photo 6' },
        ];
        
        var galleryClasses = [
            'voxel-timeline-photos',
            'layout-' + settings.gallery_layout,
            'hover-' + settings.hover_effect
        ];
        
        if (settings.gallery_layout === 'grid' && settings.aspect_ratio !== 'auto') {
            galleryClasses.push('aspect-' + settings.aspect_ratio);
        }
        
        var lightboxAttr = settings.enable_lightbox === 'yes' ? 'data-elementor-open-lightbox="yes"' : '';
        #>
        
        <div class="{{{ galleryClasses.join(' ') }}}">
            <# _.each(samplePhotos, function(photo, index) { #>
                <div class="timeline-photo-item">
                    <a href="https://via.placeholder.com/600x400/cccccc/666666?text=Photo+{{{ index + 1 }}}" 
                       {{{ lightboxAttr }}}>
                        <img src="https://via.placeholder.com/300x200/cccccc/666666?text=Photo+{{{ index + 1 }}}" 
                             alt="{{{ photo.title }}}" 
                             title="{{{ photo.title }}}">
                        <# if (settings.hover_effect !== 'none') { #>
                            <div class="photo-overlay"></div>
                        <# } #>
                    </a>
                </div>
            <# }); #>
        </div>
        <?php
    }
}