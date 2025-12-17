<?php
/**
 * Compare Posts Table Widget
 *
 * Elementor widget for displaying comparison table
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Compare_Posts_Table_Widget extends \Elementor\Widget_Base {

    /**
     * Get widget name
     *
     * @return string Widget name
     */
    public function get_name() {
        return 'voxel-toolkit-compare-table';
    }

    /**
     * Get widget title
     *
     * @return string Widget title
     */
    public function get_title() {
        return __('Comparison Table (VT)', 'voxel-toolkit');
    }

    /**
     * Get widget icon
     *
     * @return string Widget icon
     */
    public function get_icon() {
        return 'eicon-table';
    }

    /**
     * Get widget categories
     *
     * @return array Widget categories
     */
    public function get_categories() {
        return array('voxel-toolkit');
    }

    /**
     * Get widget keywords
     *
     * @return array Widget keywords
     */
    public function get_keywords() {
        return array('compare', 'comparison', 'table', 'posts', 'voxel');
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
        // Post Type Selection
        $this->start_controls_section(
            'section_post_type',
            array(
                'label' => __('Post Type', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $post_types = Voxel_Toolkit_Compare_Posts_Widget_Manager::get_voxel_post_types();
        $first_post_type = !empty($post_types) ? array_key_first($post_types) : '';

        $this->add_control(
            'post_type',
            array(
                'label' => __('Post Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => $post_types,
                'default' => $first_post_type,
                'description' => __('Select which post type this comparison table is for.', 'voxel-toolkit'),
            )
        );

        $this->end_controls_section();

        // Field Selection
        $this->start_controls_section(
            'section_fields',
            array(
                'label' => __('Fields to Compare', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        // Create repeater controls for each post type
        foreach ($post_types as $pt_key => $pt_label) {
            $fields = Voxel_Toolkit_Compare_Posts_Widget_Manager::get_post_type_fields($pt_key);

            // Add empty option at start
            $field_options = array('' => __('— Select Field —', 'voxel-toolkit')) + $fields;

            $repeater = new \Elementor\Repeater();

            $repeater->add_control(
                'field_key',
                array(
                    'label' => __('Field', 'voxel-toolkit'),
                    'type' => \Elementor\Controls_Manager::SELECT,
                    'options' => $field_options,
                    'default' => '',
                    'label_block' => true,
                )
            );

            $repeater->add_control(
                'custom_label',
                array(
                    'label' => __('Custom Label', 'voxel-toolkit'),
                    'type' => \Elementor\Controls_Manager::TEXT,
                    'default' => '',
                    'placeholder' => __('Leave empty to use field name', 'voxel-toolkit'),
                    'label_block' => true,
                )
            );

            $this->add_control(
                'compare_fields_' . $pt_key,
                array(
                    'label' => sprintf(__('Fields for %s', 'voxel-toolkit'), $pt_label),
                    'type' => \Elementor\Controls_Manager::REPEATER,
                    'fields' => $repeater->get_controls(),
                    'default' => array(),
                    'title_field' => '{{{ custom_label || field_key }}}',
                    'condition' => array(
                        'post_type' => $pt_key,
                    ),
                )
            );
        }

        $this->end_controls_section();

        // Labels Section
        $this->start_controls_section(
            'section_labels',
            array(
                'label' => __('Labels', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'feature_column_label',
            array(
                'label' => __('Feature Column Label', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Feature', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'empty_state_text',
            array(
                'label' => __('Empty State Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('No posts selected for comparison. Add posts using the Compare button.', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'remove_button_text',
            array(
                'label' => __('Remove Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Remove', 'voxel-toolkit'),
            )
        );

        $this->add_control(
            'min_posts_message',
            array(
                'label' => __('Minimum Posts Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Add at least one more post to see the comparison.', 'voxel-toolkit'),
                'description' => __('Shown when only 1 post is selected (minimum 2 required)', 'voxel-toolkit'),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Register style controls
     */
    private function register_style_controls() {
        // Table Container Style
        $this->start_controls_section(
            'section_table_style',
            array(
                'label' => __('Table', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            array(
                'name' => 'table_border',
                'selector' => '{{WRAPPER}} .vt-compare-table',
            )
        );

        $this->add_control(
            'table_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                    '{{WRAPPER}} .vt-compare-table-container' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}}; overflow: hidden;',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            array(
                'name' => 'table_box_shadow',
                'selector' => '{{WRAPPER}} .vt-compare-table-container',
            )
        );

        $this->end_controls_section();

        // Header Row Style
        $this->start_controls_section(
            'section_header_style',
            array(
                'label' => __('Header Row', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'header_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table thead th' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'header_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table thead th' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'header_typography',
                'selector' => '{{WRAPPER}} .vt-compare-table thead th',
            )
        );

        $this->add_responsive_control(
            'header_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table thead th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'header_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table thead th' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Post Header Style (thumbnails and titles)
        $this->start_controls_section(
            'section_post_header_style',
            array(
                'label' => __('Post Headers', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'post_thumbnail_size',
            array(
                'label' => __('Thumbnail Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 30,
                        'max' => 150,
                    ),
                ),
                'default' => array(
                    'size' => 60,
                    'unit' => 'px',
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-header-content img, {{WRAPPER}} .vt-compare-no-thumb-table' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'post_thumbnail_radius',
            array(
                'label' => __('Thumbnail Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-header-content img, {{WRAPPER}} .vt-compare-no-thumb-table' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'post_title_typography',
                'label' => __('Title Typography', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-compare-header-title',
            )
        );

        $this->add_control(
            'post_title_color',
            array(
                'label' => __('Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-header-title' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Field Label Column Style
        $this->start_controls_section(
            'section_field_label_style',
            array(
                'label' => __('Field Label Column', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'field_label_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody th' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'field_label_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody th' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'field_label_typography',
                'selector' => '{{WRAPPER}} .vt-compare-table tbody th',
            )
        );

        $this->add_responsive_control(
            'field_label_width',
            array(
                'label' => __('Column Width', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 100,
                        'max' => 400,
                    ),
                ),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table .vt-compare-field-header, {{WRAPPER}} .vt-compare-table .vt-compare-field-label' => 'width: {{SIZE}}{{UNIT}}; min-width: {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();

        // Cell Style
        $this->start_controls_section(
            'section_cell_style',
            array(
                'label' => __('Cells', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'cell_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody td' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cell_alt_bg_color',
            array(
                'label' => __('Alternate Row Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody tr:nth-child(even) td' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cell_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody td' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'cell_typography',
                'selector' => '{{WRAPPER}} .vt-compare-table tbody td',
            )
        );

        $this->add_responsive_control(
            'cell_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody td, {{WRAPPER}} .vt-compare-table tbody th' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'cell_border_color',
            array(
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table td, {{WRAPPER}} .vt-compare-table th' => 'border-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'cell_vertical_align',
            array(
                'label' => __('Vertical Alignment', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'options' => array(
                    'top' => __('Top', 'voxel-toolkit'),
                    'middle' => __('Middle', 'voxel-toolkit'),
                    'bottom' => __('Bottom', 'voxel-toolkit'),
                ),
                'default' => 'middle',
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-table tbody td, {{WRAPPER}} .vt-compare-table tbody th' => 'vertical-align: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Remove Button Style
        $this->start_controls_section(
            'section_remove_button_style',
            array(
                'label' => __('Remove Button', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'remove_btn_typography',
                'selector' => '{{WRAPPER}} .vt-compare-remove-btn',
            )
        );

        $this->add_control(
            'remove_btn_color',
            array(
                'label' => __('Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-remove-btn' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'remove_btn_hover_color',
            array(
                'label' => __('Hover Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-remove-btn:hover' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();

        // Empty State Style
        $this->start_controls_section(
            'section_empty_state_style',
            array(
                'label' => __('Empty State', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'empty_state_bg_color',
            array(
                'label' => __('Background Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-empty' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'empty_state_text_color',
            array(
                'label' => __('Text Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-empty' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'empty_state_typography',
                'selector' => '{{WRAPPER}} .vt-compare-empty',
            )
        );

        $this->add_responsive_control(
            'empty_state_padding',
            array(
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', 'em'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-empty' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->add_control(
            'empty_state_border_radius',
            array(
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => array('px', '%'),
                'selectors' => array(
                    '{{WRAPPER}} .vt-compare-empty' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render widget output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        $post_type = $settings['post_type'];
        $fields_key = 'compare_fields_' . $post_type;
        $repeater_fields = isset($settings[$fields_key]) ? (array) $settings[$fields_key] : array();

        // Get all available field labels
        $all_fields = Voxel_Toolkit_Compare_Posts_Widget_Manager::get_post_type_fields($post_type);

        // Build field keys array and labels from repeater
        $selected_fields = array();
        $field_labels = array();
        foreach ($repeater_fields as $item) {
            $field_key = isset($item['field_key']) ? $item['field_key'] : '';
            if (empty($field_key)) {
                continue;
            }
            $selected_fields[] = $field_key;
            // Use custom label if provided, otherwise use default field label
            $custom_label = isset($item['custom_label']) && !empty($item['custom_label']) ? $item['custom_label'] : '';
            $field_labels[$field_key] = $custom_label ? $custom_label : (isset($all_fields[$field_key]) ? $all_fields[$field_key] : $field_key);
        }

        // Check if in Elementor editor
        $is_editor = class_exists('\Elementor\Plugin') && \Elementor\Plugin::$instance->editor->is_edit_mode();

        ?>
        <div class="vt-compare-table-wrapper"
             data-post-type="<?php echo esc_attr($post_type); ?>"
             data-fields="<?php echo esc_attr(json_encode($selected_fields)); ?>"
             data-field-labels="<?php echo esc_attr(json_encode($field_labels)); ?>"
             data-remove-text="<?php echo esc_attr($settings['remove_button_text']); ?>"
             data-feature-label="<?php echo esc_attr($settings['feature_column_label']); ?>"
             data-min-posts-msg="<?php echo esc_attr($settings['min_posts_message']); ?>">

            <?php if ($is_editor): ?>
                <!-- Editor Preview with dummy data -->
                <?php $this->render_editor_preview($settings, $field_labels); ?>
            <?php else: ?>
                <!-- Empty state (shown by default, hidden by JS when posts exist) -->
                <div class="vt-compare-empty">
                    <?php echo esc_html($settings['empty_state_text']); ?>
                </div>

                <!-- Minimum posts state (shown when only 1 post selected) -->
                <div class="vt-compare-min-posts" style="display: none;">
                    <?php echo esc_html($settings['min_posts_message']); ?>
                </div>

                <!-- Table container (populated by JavaScript) -->
                <div class="vt-compare-table-container" style="display: none;">
                    <table class="vt-compare-table">
                        <thead>
                            <tr>
                                <th class="vt-compare-field-header"><?php echo esc_html($settings['feature_column_label']); ?></th>
                                <!-- Post columns added by JS -->
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows added by JS -->
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render editor preview with dummy data
     */
    private function render_editor_preview($settings, $field_labels) {
        // Dummy posts for preview
        $dummy_posts = array(
            array(
                'title' => 'Sample Listing 1',
                'thumbnail' => '',
            ),
            array(
                'title' => 'Sample Listing 2',
                'thumbnail' => '',
            ),
            array(
                'title' => 'Sample Listing 3',
                'thumbnail' => '',
            ),
        );

        // Sample values for different field types
        $sample_values = array(
            'Sample text value',
            '$250 - $500',
            '4.8 / 5 stars',
            'Yes',
            'Premium',
            '123 Main Street, City',
            'contact@example.com',
            '+1 (555) 123-4567',
            'Available Now',
            '2,500 sq ft',
        );
        ?>
        <div class="vt-compare-table-container">
            <table class="vt-compare-table">
                <thead>
                    <tr>
                        <th class="vt-compare-field-header"><?php echo esc_html($settings['feature_column_label']); ?></th>
                        <?php foreach ($dummy_posts as $index => $post): ?>
                            <th class="vt-compare-post-header">
                                <div class="vt-compare-header-content">
                                    <div class="vt-compare-no-thumb-table">
                                        <span class="dashicons dashicons-format-image"></span>
                                    </div>
                                    <span class="vt-compare-header-title"><?php echo esc_html($post['title']); ?></span>
                                    <button class="vt-compare-remove-btn" type="button">
                                        <?php echo esc_html($settings['remove_button_text']); ?>
                                    </button>
                                </div>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $value_index = 0;
                    if (empty($field_labels)):
                        // Show placeholder rows if no fields selected
                        for ($i = 0; $i < 4; $i++):
                    ?>
                        <tr>
                            <th class="vt-compare-field-label">Field <?php echo $i + 1; ?></th>
                            <?php foreach ($dummy_posts as $post): ?>
                                <td><?php echo esc_html($sample_values[$value_index % count($sample_values)]); ?></td>
                            <?php $value_index++; endforeach; ?>
                        </tr>
                    <?php
                        endfor;
                    else:
                        foreach ($field_labels as $field_key => $field_label):
                    ?>
                        <tr>
                            <th class="vt-compare-field-label"><?php echo esc_html($field_label); ?></th>
                            <?php foreach ($dummy_posts as $post): ?>
                                <td><?php echo esc_html($sample_values[$value_index % count($sample_values)]); ?></td>
                            <?php $value_index++; endforeach; ?>
                        </tr>
                    <?php
                        endforeach;
                    endif;
                    ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}
