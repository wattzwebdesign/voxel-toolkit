<?php
/**
 * Media Gallery Widget
 *
 * Extends Voxel's Gallery widget to support:
 * - Files field as dynamic source
 * - Video files with thumbnail + play icon overlay
 * - Video lightbox playback
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Widgets;

if (!defined('ABSPATH')) {
    exit;
}

// Check if Voxel Gallery widget exists
if (!class_exists('\Voxel\Widgets\Gallery')) {
    return;
}

class Media_Gallery_Widget extends \Voxel\Widgets\Gallery {

    /**
     * Get widget name
     */
    public function get_name() {
        return 'vt-media-gallery';
    }

    /**
     * Get widget title
     */
    public function get_title() {
        return __('Media Gallery (VT)', 'voxel-toolkit');
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
        return ['voxel', 'basic'];
    }

    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['gallery', 'media', 'video', 'photo', 'files', 'voxel'];
    }

    /**
     * Register widget controls
     */
    protected function register_controls() {
        // Media Source section
        $this->start_controls_section(
            'vt_media_source',
            [
                'label' => __('Media', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'vt_files_field',
            [
                'label' => __('Files Field', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'dynamic' => [
                    'active' => true,
                    'categories' => [
                        \Elementor\Modules\DynamicTags\Module::TEXT_CATEGORY,
                    ],
                ],
                'description' => __('Use dynamic tag to select a files field', 'voxel-toolkit'),
            ]
        );

        $this->add_control( 'ts_visible_count', [
            'label' => __( 'Number of items to show', 'voxel-toolkit' ),
            'type' => \Elementor\Controls_Manager::NUMBER,
            'default' => 3,
        ] );

        $this->add_responsive_control( 'ts_display_size', [
            'label' => __( 'Image size', 'voxel-toolkit' ),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'medium',
            'options' => \Voxel\get_image_sizes_with_labels(),
        ] );

        $this->add_responsive_control( 'ts_lightbox_size', [
            'label' => __( 'Image size (Lightbox)', 'voxel-toolkit' ),
            'type' => \Elementor\Controls_Manager::SELECT,
            'default' => 'large',
            'options' => \Voxel\get_image_sizes_with_labels(),
        ] );

        $this->add_control(
            'vt_video_handling',
            [
                'label' => __('Video Settings', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'vt_show_play_icon',
            [
                'label' => __('Show Play Icon on Videos', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'vt_play_icon',
            [
                'label' => __('Play Icon', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-play',
                    'library' => 'fa-solid',
                ],
                'condition' => [
                    'vt_show_play_icon' => 'yes',
                ],
            ]
        );

        $this->add_control(
            'vt_video_thumbnail_time',
            [
                'label' => __('Thumbnail Capture Time', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['s'],
                'range' => [
                    's' => [
                        'min' => 0,
                        'max' => 10,
                        'step' => 0.1,
                    ],
                ],
                'default' => [
                    'unit' => 's',
                    'size' => 1,
                ],
                'description' => __('Time in seconds to capture the video thumbnail (e.g., 1s = ~30th frame)', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'ts_gl_column',
            [
                'label' => __( 'Grid Layout', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'ts_gl_col_gap',
            [
                'label' => __( 'Item gap', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery-grid' => 'grid-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'ts_gl_column_no',
            [
                'label' => __( 'Number of columns', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 6,
                'step' => 1,
                'default' => 3,
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery-grid' => 'grid-template-columns: repeat({{VALUE}}, minmax(0, 1fr));',
                ],
            ]
        );

        $this->add_control(
            'ts_remove_empty',
            [
                'label' => __( 'Remove empty items?', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'selectors' => [
                    '{{WRAPPER}} .ts-empty-item' => 'display: none;',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_autofit',
            [
                'label' => __( 'Auto fit?', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'return_value' => 'yes',
                'condition' => [ 'ts_remove_empty' => 'yes' ],
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery-grid' => 'grid-template-columns: repeat(auto-fit, minmax(0, 1fr));',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_row',
            [
                'label' => __( 'Row height', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'ts_gl_row_height',
            [
                'label' => __( 'Set height', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px'],
                'condition' => [ 'aspect-ratio-row' => '' ],
                'range' => [
                    'px' => [
                        'min' => 50,
                        'max' => 500,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 250,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery-grid' => 'grid-auto-rows: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control( 'aspect-ratio-row', [
            'label' => __( 'Use aspect ratio instead?', 'voxel-toolkit' ),
            'type' => \Elementor\Controls_Manager::SWITCHER,
            'return_value' => 'yes',
            'selectors' => [
                '{{WRAPPER}} .ts-gallery-grid' => 'grid-auto-rows: auto;',
            ],
        ] );

        $this->add_responsive_control( 'vx_paragraph_gap', [
            'label' => __( 'Aspect ratio', 'voxel-toolkit' ),
            'description' => __( 'Set image aspect ratio e.g 16/9', 'voxel-toolkit' ),
            'type' => \Elementor\Controls_Manager::TEXT,
            'condition' => [ 'aspect-ratio-row' => 'yes' ],
            'selectors' => [
                '{{WRAPPER}} .ts-gallery li > *' => 'aspect-ratio: {{VALUE}}; object-fit: cover;',
            ],
        ] );

        $this->end_controls_section();

        // Register style controls from parent (skip the content sections)
        $this->register_style_controls();

        // Add video styling section
        $this->start_controls_section(
            'vt_video_style',
            [
                'label' => __('Video Items', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'vt_play_icon_color',
            [
                'label' => __('Play Icon Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .vt-video-item .vt-play-icon i' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .vt-video-item .vt-play-icon svg' => 'fill: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_play_icon_size',
            [
                'label' => __('Play Icon Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 48,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-video-item .vt-play-icon i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .vt-video-item .vt-play-icon svg' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'vt_play_icon_bg',
            [
                'label' => __('Play Icon Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => 'rgba(0,0,0,0.5)',
                'selectors' => [
                    '{{WRAPPER}} .vt-video-item .vt-play-icon' => 'background: {{VALUE}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_play_icon_padding',
            [
                'label' => __('Play Icon Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-video-item .vt-play-icon' => 'padding: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_responsive_control(
            'vt_play_icon_radius',
            [
                'label' => __('Play Icon Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px', '%'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 50,
                    ],
                ],
                'default' => [
                    'unit' => '%',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-video-item .vt-play-icon' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls (gallery styling from Voxel)
     */
    protected function register_style_controls() {
        $this->start_controls_section(
            'ts_gallery_general',
            [
                'label' => __( 'General', 'voxel-toolkit' ),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('ts_gl_general_tabs');

        // Normal tab
        $this->start_controls_tab(
            'ts_gl_general_normal',
            ['label' => __( 'Normal', 'voxel-toolkit' )]
        );

        $this->add_control(
            'ts_gl_general_image',
            [
                'label' => __( 'Image', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_responsive_control(
            'ts_gl_general_image_radius',
            [
                'label' => __( 'Border radius', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery li a, {{WRAPPER}} .ts-empty-item > div' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_general_overlay',
            [
                'label' => __( 'Overlay', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'ts_gl_overlay',
            [
                'label' => __( 'Overlay background color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery li .ts-image-overlay' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_empty_item',
            [
                'label' => __( 'Empty item', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'ts_gl_empty_border',
                'label' => __( 'Border', 'voxel-toolkit' ),
                'selector' => '{{WRAPPER}} .ts-gallery li.ts-empty-item div',
            ]
        );

        $this->add_control(
            'ts_gl_general_view',
            [
                'label' => __( 'View all button', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'ts_gl_general_view_bg',
            [
                'label' => __( 'Background color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item .ts-image-overlay' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_general_view_color',
            [
                'label' => __( 'Icon color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} li.ts-gallery-last-item .ts-image-overlay svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_general_view_icon',
            [
                'label' => __( 'Icon', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::ICONS,
            ]
        );

        $this->add_responsive_control(
            'ts_gl_general_view_icon_size',
            [
                'label' => __( 'Icon size', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => [ 'px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 70,
                        'step' => 1,
                    ],
                ],
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item i' => 'font-size: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} li.ts-gallery-last-item .ts-image-overlay svg' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_view_text',
            [
                'label' => __( 'Text color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item p' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'ts_gl_view_typo',
                'label' => __( 'Typography', 'voxel-toolkit' ),
                'selector' => '{{WRAPPER}} li.ts-gallery-last-item p',
            ]
        );

        $this->end_controls_tab();

        // Hover tab
        $this->start_controls_tab(
            'ts_gl_general_hover',
            ['label' => __( 'Hover', 'voxel-toolkit' )]
        );

        $this->add_control(
            'ts_gl_general_overlay_h',
            [
                'label' => __( 'Overlay', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'ts_gl_overlay_h',
            [
                'label' => __( 'Overlay background color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery li a:hover .ts-image-overlay' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_general_view_h',
            [
                'label' => __( 'View all button', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->add_control(
            'ts_gl_general_view_bg_h',
            [
                'label' => __( 'Background color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .ts-gallery li.ts-gallery-last-item:hover .ts-image-overlay' => 'background: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_general_view_color_h',
            [
                'label' => __( 'Icon color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item:hover i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} li.ts-gallery-last-item:hover .ts-image-overlay svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'ts_gl_view_text_h',
            [
                'label' => __( 'Text color', 'voxel-toolkit' ),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} li.ts-gallery-last-item:hover p' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render($instance = []) {
        $this->render_dynamic_gallery();
    }

    /**
     * Render gallery from dynamic files field
     */
    protected function render_dynamic_gallery() {
        $files_field_value = $this->get_settings_for_display('vt_files_field');
        $visible_count = (int) $this->get_settings_for_display('ts_visible_count');
        $display_size = $this->get_settings_for_display('ts_display_size');
        $lightbox_size = $this->get_settings_for_display('ts_lightbox_size');
        $show_play_icon = $this->get_settings_for_display('vt_show_play_icon') === 'yes';
        $play_icon = $this->get_settings_for_display('vt_play_icon');
        $thumbnail_time_setting = $this->get_settings_for_display('vt_video_thumbnail_time');
        $thumbnail_time = isset($thumbnail_time_setting['size']) ? floatval($thumbnail_time_setting['size']) : 1;

        // Parse attachment IDs from the files field value
        $attachment_ids = $this->parse_files_field($files_field_value);

        if (empty($attachment_ids)) {
            return;
        }

        $media_items = [];
        foreach ($attachment_ids as $attachment_id) {
            $attachment = get_post($attachment_id);
            if (!$attachment) {
                continue;
            }

            $mime_type = $attachment->post_mime_type;
            $is_video = $this->is_video_mime_type($mime_type);

            if ($is_video) {
                // Video item
                $video_url = wp_get_attachment_url($attachment_id);
                $thumbnail = $this->get_video_thumbnail($attachment_id, $display_size);

                $media_items[] = [
                    'type' => 'video',
                    'id' => $attachment_id,
                    'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                    'caption' => wp_get_attachment_caption($attachment_id),
                    'description' => $attachment->post_content,
                    'title' => $attachment->post_title,
                    'src_display' => $thumbnail,
                    'src_lightbox' => $video_url,
                    'video_url' => $video_url,
                    'mime_type' => $mime_type,
                    'display_size' => $display_size,
                ];
            } else {
                // Image item
                $src_display = wp_get_attachment_image_src($attachment_id, $display_size);
                if (!$src_display) {
                    continue;
                }

                $src_large = wp_get_attachment_image_src($attachment_id, $lightbox_size);
                if (!$src_large) {
                    $src_large = $src_display;
                }

                $media_items[] = [
                    'type' => 'image',
                    'id' => $attachment_id,
                    'alt' => get_post_meta($attachment_id, '_wp_attachment_image_alt', true),
                    'caption' => wp_get_attachment_caption($attachment_id),
                    'description' => $attachment->post_content,
                    'title' => $attachment->post_title,
                    'src_display' => $src_display[0],
                    'src_lightbox' => $src_large[0],
                    'display_size' => $display_size,
                ];
            }
        }

        if (empty($media_items)) {
            return;
        }

        // Split into visible and hidden
        if (count($media_items) <= $visible_count) {
            $visible = $media_items;
            $hidden = [];
        } else {
            $visible = array_slice($media_items, 0, $visible_count - 1);
            $hidden = array_slice($media_items, $visible_count - 1);
        }

        $is_slideshow = count($media_items) > 1;
        $filler_count = 0;
        if ($visible_count > count($media_items)) {
            $filler_count = $visible_count - count($media_items);
        }

        $current_post = \Voxel\get_current_post();
        $gallery_id = sprintf('%s-%s-%s', $this->get_id(), $current_post ? $current_post->get_id() : 0, wp_unique_id());

        // Load styles
        wp_print_styles($this->get_style_depends());

        // Render the template
        $this->render_media_gallery_template($visible, $hidden, $is_slideshow, $gallery_id, $filler_count, $show_play_icon, $play_icon, $thumbnail_time);
    }

    /**
     * Parse files field value to get attachment IDs
     *
     * @param string $value Files field value (comma-separated IDs or JSON)
     * @return array Attachment IDs
     */
    protected function parse_files_field($value) {
        if (empty($value)) {
            return [];
        }

        // Try JSON decode first (for object list format)
        $decoded = json_decode($value, true);
        if (is_array($decoded)) {
            $ids = [];
            foreach ($decoded as $item) {
                if (isset($item['id'])) {
                    $ids[] = absint($item['id']);
                } elseif (is_numeric($item)) {
                    $ids[] = absint($item);
                }
            }
            return array_filter($ids);
        }

        // Otherwise treat as comma-separated IDs
        $ids = array_map('trim', explode(',', $value));
        $ids = array_map('absint', $ids);
        return array_filter($ids);
    }

    /**
     * Check if MIME type is a video
     *
     * @param string $mime_type
     * @return bool
     */
    protected function is_video_mime_type($mime_type) {
        return strpos($mime_type, 'video/') === 0;
    }

    /**
     * Get video thumbnail URL
     *
     * @param int $attachment_id
     * @param string $size
     * @return string
     */
    protected function get_video_thumbnail($attachment_id, $size = 'medium') {
        // Check for video thumbnail meta
        $thumbnail_id = get_post_meta($attachment_id, '_thumbnail_id', true);
        if ($thumbnail_id) {
            $src = wp_get_attachment_image_src($thumbnail_id, $size);
            if ($src) {
                return $src[0];
            }
        }

        // Check for wp-video-thumbnails plugin meta
        $video_thumbnail = get_post_meta($attachment_id, '_video_thumbnail', true);
        if ($video_thumbnail) {
            return $video_thumbnail;
        }

        // Fallback: use placeholder or first frame if available
        // For now, return a placeholder image
        return VOXEL_TOOLKIT_PLUGIN_URL . 'assets/images/video-placeholder.svg';
    }

    /**
     * Render the media gallery template
     */
    protected function render_media_gallery_template($visible, $hidden, $is_slideshow, $gallery_id, $filler_count, $show_play_icon, $play_icon, $thumbnail_time = 1) {
        ?>
        <ul class="ts-gallery vt-media-gallery flexify simplify-ul" data-gallery-id="<?php echo esc_attr($gallery_id); ?>" data-thumbnail-time="<?php echo esc_attr($thumbnail_time); ?>">
            <div class="ts-gallery-grid">
                <?php foreach ($visible as $item): ?>
                    <li class="<?php echo $item['type'] === 'video' ? 'vt-video-item' : 'vt-image-item'; ?>">
                        <?php if ($item['type'] === 'video'): ?>
                            <a
                                href="<?php echo esc_url($item['video_url']); ?>"
                                class="vt-video-link"
                                data-vt-video="<?php echo esc_url($item['video_url']); ?>"
                                data-vt-video-type="<?php echo esc_attr($item['mime_type']); ?>"
                            >
                                <div class="ts-image-overlay"></div>
                                <?php if ($show_play_icon): ?>
                                    <div class="vt-play-icon">
                                        <?php echo \Voxel\get_icon_markup($play_icon) ?: '<i class="fas fa-play"></i>'; ?>
                                    </div>
                                <?php endif; ?>
                                <img class="vt-video-thumbnail" src="<?php echo esc_url($item['src_display']); ?>" data-video-src="<?php echo esc_url($item['video_url']); ?>" alt="<?php echo esc_attr($item['alt'] ?: $item['description']); ?>">
                            </a>
                        <?php else: ?>
                            <a
                                href="<?php echo esc_url($item['src_lightbox']); ?>"
                                data-elementor-open-lightbox="yes"
                                <?php echo $is_slideshow ? sprintf('data-elementor-lightbox-slideshow="%s"', esc_attr($gallery_id)) : ''; ?>
                                data-elementor-lightbox-description="<?php echo esc_attr($item['caption'] ?: ($item['alt'] ?: $item['description'])); ?>"
                            >
                                <div class="ts-image-overlay"></div>
                                <?php echo wp_get_attachment_image($item['id'], $item['display_size'], false, ['alt' => esc_attr($item['alt'] ?: $item['description'])]); ?>
                            </a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>

                <?php if (count($hidden)): ?>
                    <li class="ts-gallery-last-item <?php echo $hidden[0]['type'] === 'video' ? 'vt-video-item' : 'vt-image-item'; ?>">
                        <?php if ($hidden[0]['type'] === 'video'): ?>
                            <a
                                href="<?php echo esc_url($hidden[0]['video_url']); ?>"
                                class="vt-video-link"
                                data-vt-video="<?php echo esc_url($hidden[0]['video_url']); ?>"
                                data-vt-video-type="<?php echo esc_attr($hidden[0]['mime_type']); ?>"
                            >
                                <div class="ts-image-overlay">
                                    <?php echo \Voxel\get_icon_markup($this->get_settings_for_display('ts_gl_general_view_icon')) ?: \Voxel\svg('grid.svg'); ?>
                                    <p><?php echo sprintf('+%d', count($hidden)); ?></p>
                                </div>
                                <img class="vt-video-thumbnail" src="<?php echo esc_url($hidden[0]['src_display']); ?>" data-video-src="<?php echo esc_url($hidden[0]['video_url']); ?>" alt="<?php echo esc_attr($hidden[0]['alt'] ?: $hidden[0]['description']); ?>">
                            </a>
                        <?php else: ?>
                            <a
                                href="<?php echo esc_url($hidden[0]['src_lightbox']); ?>"
                                data-elementor-open-lightbox="yes"
                                <?php echo $is_slideshow ? sprintf('data-elementor-lightbox-slideshow="%s"', esc_attr($gallery_id)) : ''; ?>
                                data-elementor-lightbox-description="<?php echo esc_attr($hidden[0]['caption'] ?: ($hidden[0]['alt'] ?: $hidden[0]['description'])); ?>"
                            >
                                <div class="ts-image-overlay">
                                    <?php echo \Voxel\get_icon_markup($this->get_settings_for_display('ts_gl_general_view_icon')) ?: \Voxel\svg('grid.svg'); ?>
                                    <p><?php echo sprintf('+%d', count($hidden)); ?></p>
                                </div>
                                <?php echo wp_get_attachment_image($hidden[0]['id'], $hidden[0]['display_size'], false, ['alt' => esc_attr($hidden[0]['alt'] ?: $hidden[0]['description'])]); ?>
                            </a>
                        <?php endif; ?>

                        <div class="hidden">
                            <?php foreach ($hidden as $index => $item): ?>
                                <?php if ($index === 0) continue; ?>
                                <?php if ($item['type'] === 'video'): ?>
                                    <a
                                        href="<?php echo esc_url($item['video_url']); ?>"
                                        class="vt-video-link"
                                        data-vt-video="<?php echo esc_url($item['video_url']); ?>"
                                        data-vt-video-type="<?php echo esc_attr($item['mime_type']); ?>"
                                    ></a>
                                <?php else: ?>
                                    <a
                                        href="<?php echo esc_url($item['src_lightbox']); ?>"
                                        data-elementor-open-lightbox="yes"
                                        data-elementor-lightbox-slideshow="<?php echo esc_attr($gallery_id); ?>"
                                        data-elementor-lightbox-description="<?php echo esc_attr($item['caption'] ?: ($item['alt'] ?: $item['description'])); ?>"
                                    ></a>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </li>
                <?php endif; ?>

                <?php if ($filler_count >= 1): ?>
                    <?php while ($filler_count >= 1): $filler_count--; ?>
                        <li class="ts-empty-item">
                            <div></div>
                        </li>
                    <?php endwhile; ?>
                <?php endif; ?>
            </div>
        </ul>

        <!-- Video Lightbox Modal -->
        <div class="vt-video-lightbox" id="vt-video-lightbox-<?php echo esc_attr($gallery_id); ?>" style="display: none;">
            <div class="vt-video-lightbox-backdrop"></div>
            <div class="vt-video-lightbox-content">
                <button class="vt-video-lightbox-close" type="button" aria-label="<?php esc_attr_e('Close', 'voxel-toolkit'); ?>">
                    <svg viewBox="0 0 24 24" width="24" height="24" fill="currentColor">
                        <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                    </svg>
                </button>
                <video class="vt-video-player" controls playsinline>
                    <source src="" type="">
                </video>
            </div>
        </div>

        <script>
        (function() {
            var galleryId = '<?php echo esc_js($gallery_id); ?>';
            var gallery = document.querySelector('[data-gallery-id="' + galleryId + '"]');
            var lightbox = document.getElementById('vt-video-lightbox-' + galleryId);

            if (!gallery || !lightbox) return;

            var video = lightbox.querySelector('.vt-video-player');
            var source = video.querySelector('source');
            var closeBtn = lightbox.querySelector('.vt-video-lightbox-close');
            var backdrop = lightbox.querySelector('.vt-video-lightbox-backdrop');
            var thumbnailTime = parseFloat(gallery.getAttribute('data-thumbnail-time')) || 1;

            // Generate video thumbnails from specified time
            function generateVideoThumbnail(imgElement, videoSrc, seekTime) {
                var tempVideo = document.createElement('video');
                tempVideo.crossOrigin = 'anonymous';
                tempVideo.muted = true;
                tempVideo.preload = 'metadata';

                tempVideo.addEventListener('loadedmetadata', function() {
                    // Ensure we don't seek past the video duration
                    var targetTime = Math.min(seekTime, tempVideo.duration - 0.1);
                    if (targetTime < 0) targetTime = 0;
                    tempVideo.currentTime = targetTime;
                });

                tempVideo.addEventListener('seeked', function() {
                    var canvas = document.createElement('canvas');
                    canvas.width = tempVideo.videoWidth;
                    canvas.height = tempVideo.videoHeight;

                    var ctx = canvas.getContext('2d');
                    ctx.drawImage(tempVideo, 0, 0, canvas.width, canvas.height);

                    try {
                        var dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                        imgElement.src = dataUrl;
                    } catch (e) {
                        // CORS error - keep the placeholder
                        console.log('Could not generate thumbnail due to CORS');
                    }

                    // Clean up
                    tempVideo.src = '';
                    tempVideo.load();
                });

                tempVideo.addEventListener('error', function() {
                    // Keep placeholder on error
                    console.log('Could not load video for thumbnail');
                });

                tempVideo.src = videoSrc;
            }

            // Process all video thumbnails in this gallery
            var videoThumbnails = gallery.querySelectorAll('.vt-video-thumbnail[data-video-src]');
            videoThumbnails.forEach(function(img) {
                var videoSrc = img.getAttribute('data-video-src');
                if (videoSrc) {
                    generateVideoThumbnail(img, videoSrc, thumbnailTime);
                }
            });

            // Handle video link clicks
            gallery.addEventListener('click', function(e) {
                var link = e.target.closest('.vt-video-link');
                if (!link) return;

                e.preventDefault();

                var videoUrl = link.getAttribute('data-vt-video');
                var videoType = link.getAttribute('data-vt-video-type');

                source.src = videoUrl;
                source.type = videoType;
                video.load();

                lightbox.style.display = 'flex';
                document.body.style.overflow = 'hidden';

                video.play();
            });

            // Close lightbox
            function closeLightbox() {
                video.pause();
                video.currentTime = 0;
                lightbox.style.display = 'none';
                document.body.style.overflow = '';
            }

            closeBtn.addEventListener('click', closeLightbox);
            backdrop.addEventListener('click', closeLightbox);

            // Close on Escape key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && lightbox.style.display === 'flex') {
                    closeLightbox();
                }
            });
        })();
        </script>
        <?php
    }

    /**
     * Get style dependencies
     */
    public function get_style_depends() {
        return ['vx:gallery.css', 'e-swiper', 'vt-media-gallery'];
    }
}
