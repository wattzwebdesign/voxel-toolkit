<?php
/**
 * Users Purchased Elementor Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Users_Purchased_Widget extends \Elementor\Widget_Base {
    
    /**
     * Get widget name
     */
    public function get_name() {
        return 'voxel-toolkit-users-purchased';
    }
    
    /**
     * Get widget title
     */
    public function get_title() {
        return __('Users Purchased (VT)', 'voxel-toolkit');
    }
    
    /**
     * Get widget icon
     */
    public function get_icon() {
        return 'eicon-cart';
    }
    
    /**
     * Get widget categories
     */
    public function get_categories() {
        return ['voxel-toolkit'];
    }
    
    /**
     * Get widget keywords
     */
    public function get_keywords() {
        return ['users', 'purchased', 'customers', 'orders', 'buyers'];
    }
    
    /**
     * Register widget controls
     */
    protected function _register_controls() {
        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Display Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );
        
        $this->add_control(
            'view_type',
            [
                'label' => __('View Type', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'avatar',
                'options' => [
                    'avatar' => __('Avatar Grid', 'voxel-toolkit'),
                    'list' => __('User List', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'post_source',
            [
                'label' => __('Post Source', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current' => __('Current Post', 'voxel-toolkit'),
                    'custom' => __('Custom Post ID', 'voxel-toolkit'),
                ],
            ]
        );
        
        $this->add_control(
            'custom_post_id',
            [
                'label' => __('Custom Post ID', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'condition' => [
                    'post_source' => 'custom',
                ],
            ]
        );
        
        $this->add_control(
            'limit',
            [
                'label' => __('Number of Users to Show', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 10,
                'min' => 1,
                'max' => 100,
            ]
        );
        
        $this->add_control(
            'show_count',
            [
                'label' => __('Show Total Count', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'count_text',
            [
                'label' => __('Count Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('people purchased this', 'voxel-toolkit'),
                'placeholder' => __('people purchased this', 'voxel-toolkit'),
                'condition' => [
                    'show_count' => 'yes',
                ],
                'dynamic' => [
                    'active' => true,
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Avatar View Settings
        $this->start_controls_section(
            'avatar_settings',
            [
                'label' => __('Avatar Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'view_type' => 'avatar',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'avatar_size',
            [
                'label' => __('Avatar Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 150,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 50,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'avatar_overlap',
            [
                'label' => __('Avatar Overlap (Horizontal)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => -300,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => -10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar:not(:first-child)' => 'margin-left: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'avatar_row_spacing',
            [
                'label' => __('Row Spacing (Vertical)', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 10,
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-avatar' => 'row-gap: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_control(
            'reverse_stacking',
            [
                'label' => __('Stacking Order', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Left on Top', 'voxel-toolkit'),
                'label_off' => __('Right on Top', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
                'description' => __('Choose which avatars appear on top: left avatars (first users) or right avatars (recent users).', 'voxel-toolkit'),
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'avatar_border',
                'selector' => '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar',
            ]
        );
        
        $this->add_control(
            'avatar_border_color',
            [
                'label' => __('Border Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar' => 'border-color: {{VALUE}};',
                ],
            ]
        );
        
        $this->add_control(
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
                    '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'avatar_box_shadow',
                'selector' => '{{WRAPPER}} .vt-users-purchased-avatar .vt-avatar',
            ]
        );
        
        $this->end_controls_section();
        
        // List View Settings
        $this->start_controls_section(
            'list_settings',
            [
                'label' => __('List Settings', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
                'condition' => [
                    'view_type' => 'list',
                ],
            ]
        );
        
        $this->add_control(
            'show_avatar_in_list',
            [
                'label' => __('Show Avatar', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_display_name',
            [
                'label' => __('Show Display Name', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_control(
            'show_email',
            [
                'label' => __('Show Email', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );
        
        $this->add_control(
            'email_max_length',
            [
                'label' => __('Email Max Length', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::NUMBER,
                'default' => 20,
                'min' => 5,
                'max' => 50,
                'condition' => [
                    'show_email' => 'yes',
                ],
                'description' => __('Maximum characters to show. Longer emails will be truncated with tooltip.', 'voxel-toolkit'),
            ]
        );
        
        $this->add_control(
            'show_purchase_date',
            [
                'label' => __('Show Purchase Date', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => __('Show', 'voxel-toolkit'),
                'label_off' => __('Hide', 'voxel-toolkit'),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );
        
        $this->add_responsive_control(
            'list_avatar_size',
            [
                'label' => __('List Avatar Size', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 40,
                ],
                'condition' => [
                    'show_avatar_in_list' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-list .vt-user-item .vt-avatar' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'name_bottom_spacing',
            [
                'label' => __('Name Bottom Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 5,
                ],
                'condition' => [
                    'show_display_name' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-user-name' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'email_bottom_spacing',
            [
                'label' => __('Email Bottom Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 3,
                ],
                'condition' => [
                    'show_email' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-user-email' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'date_bottom_spacing',
            [
                'label' => __('Date Bottom Spacing', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 30,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 0,
                ],
                'condition' => [
                    'show_purchase_date' => 'yes',
                ],
                'selectors' => [
                    '{{WRAPPER}} .vt-purchase-date' => 'margin-bottom: {{SIZE}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Container Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'container_background',
                'label' => __('Background', 'voxel-toolkit'),
                'types' => ['classic', 'gradient'],
                'selector' => '{{WRAPPER}} .vt-users-purchased-widget',
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'container_border',
                'selector' => '{{WRAPPER}} .vt-users-purchased-widget',
            ]
        );
        
        $this->add_control(
            'container_border_radius',
            [
                'label' => __('Border Radius', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%'],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-widget' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->add_responsive_control(
            'container_padding',
            [
                'label' => __('Padding', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::DIMENSIONS,
                'size_units' => ['px', '%', 'em'],
                'selectors' => [
                    '{{WRAPPER}} .vt-users-purchased-widget' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );
        
        $this->end_controls_section();
        
        // Typography Section
        $this->start_controls_section(
            'typography_section',
            [
                'label' => __('Typography', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'count_typography',
                'label' => __('Count Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-users-count',
                'condition' => [
                    'show_count' => 'yes',
                ],
            ]
        );
        
        $this->add_control(
            'count_color',
            [
                'label' => __('Count Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-users-count' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'show_count' => 'yes',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'list_name_typography',
                'label' => __('Name Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-user-name',
                'condition' => [
                    'view_type' => 'list',
                ],
            ]
        );
        
        $this->add_control(
            'list_name_color',
            [
                'label' => __('Name Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-user-name' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'view_type' => 'list',
                ],
            ]
        );
        
        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'list_meta_typography',
                'label' => __('Meta Text', 'voxel-toolkit'),
                'selector' => '{{WRAPPER}} .vt-user-meta',
                'condition' => [
                    'view_type' => 'list',
                ],
            ]
        );
        
        $this->add_control(
            'list_meta_color',
            [
                'label' => __('Meta Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-user-meta' => 'color: {{VALUE}};',
                ],
                'condition' => [
                    'view_type' => 'list',
                ],
            ]
        );
        
        $this->end_controls_section();
    }
    
    /**
     * Render the widget
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Get post ID
        $post_id = $this->get_post_id($settings);
        
        if (!$post_id) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="vt-users-purchased-widget" style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center;">';
                echo '<p style="margin: 0; color: #999;">No valid post ID found.</p>';
                echo '</div>';
            }
            return;
        }
        
        // Get purchased users
        $users_data = $this->get_purchased_users($post_id, $settings['limit']);
        
        if (empty($users_data['users'])) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="vt-users-purchased-widget" style="padding: 20px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 4px; text-align: center;">';
                echo '<p style="margin: 0; color: #999;">No users have purchased this product yet.</p>';
                echo '</div>';
            }
            return;
        }
        
        $this->render_users($users_data, $settings);
    }
    
    /**
     * Get post ID based on settings
     */
    private function get_post_id($settings) {
        if ($settings['post_source'] === 'custom' && !empty($settings['custom_post_id'])) {
            return intval($settings['custom_post_id']);
        }
        
        return get_the_ID();
    }
    
    /**
     * Get users who purchased the product
     */
    private function get_purchased_users($post_id, $limit = 10) {
        global $wpdb;
        
        // Create cache key
        $cache_key = 'vt_users_purchased_' . $post_id . '_' . $limit;
        
        // Check cache first (5 minutes)
        $cached_data = get_transient($cache_key);
        if ($cached_data !== false) {
            return $cached_data;
        }
        
        // Query to get all orders with the specific status
        $orders_query = "
            SELECT customer_id, details, created_at
            FROM {$wpdb->prefix}vx_orders 
            WHERE status = 'completed'
            ORDER BY created_at DESC
        ";
        
        $orders = $wpdb->get_results($orders_query);
        
        $matching_users = array();
        $processed_users = array();
        $all_matching_users = array(); // Store all users for proper counting
        
        // First pass: collect all matching users
        foreach ($orders as $order) {
            // Parse the JSON details
            $details = json_decode($order->details, true);
            if (!$details || !isset($details['cart']['items'])) {
                continue;
            }
            
            // Check if this order contains the specific post_id
            $found_product = false;
            foreach ($details['cart']['items'] as $item) {
                if (isset($item['product']['post_id']) && intval($item['product']['post_id']) === intval($post_id)) {
                    $found_product = true;
                    break;
                }
            }
            
            if (!$found_product) {
                continue;
            }
            
            // Skip if we've already processed this user
            if (in_array($order->customer_id, $processed_users)) {
                continue;
            }
            
            $processed_users[] = $order->customer_id;
            
            // Get user data
            $user = get_user_by('id', $order->customer_id);
            if (!$user) {
                continue;
            }
            
            // Get user avatar from meta
            $avatar_id = get_user_meta($order->customer_id, 'voxel:avatar', true);
            $avatar_url = '';
            
            if ($avatar_id) {
                $avatar_url = wp_get_attachment_image_url($avatar_id, 'thumbnail');
            }
            
            // Fallback to gravatar if no custom avatar
            if (!$avatar_url) {
                $avatar_url = get_avatar_url($order->customer_id, array('size' => 100));
            }
            
            $user_data = array(
                'id' => $order->customer_id,
                'display_name' => $user->display_name,
                'email' => $user->user_email,
                'avatar_url' => $avatar_url,
                'purchase_date' => $order->created_at,
            );
            
            $all_matching_users[] = $user_data;
            
            // Add to display array only if within limit
            if (count($matching_users) < $limit) {
                $matching_users[] = $user_data;
            }
        }
        
        $result = array(
            'users' => $matching_users,
            'total_count' => count($all_matching_users),
        );
        
        // Cache the result for 5 minutes
        set_transient($cache_key, $result, 300);
        
        return $result;
    }
    
    /**
     * Render users display
     */
    private function render_users($users_data, $settings) {
        ?>
        <div class="vt-users-purchased-widget">
            <?php if ($settings['show_count'] === 'yes' && $users_data['total_count'] > 0): ?>
                <div class="vt-users-count">
                    <?php 
                    $count_text = !empty($settings['count_text']) ? $settings['count_text'] : __('people purchased this', 'voxel-toolkit');
                    echo esc_html($users_data['total_count']) . ' ' . esc_html($count_text); 
                    ?>
                </div>
            <?php endif; ?>
            
            <?php if ($settings['view_type'] === 'avatar'): ?>
                <?php $this->render_avatar_view($users_data['users'], $settings); ?>
            <?php else: ?>
                <?php $this->render_list_view($users_data['users'], $settings); ?>
            <?php endif; ?>
        </div>
        
        <style>
        .vt-users-purchased-widget {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .vt-users-count {
            margin-bottom: 15px;
            font-weight: 600;
        }
        .vt-users-purchased-avatar {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            row-gap: 10px;
        }
        .vt-users-purchased-avatar .vt-avatar {
            display: block;
            position: relative;
            z-index: 1;
            transition: all 0.2s ease;
            overflow: hidden;
        }
        .vt-users-purchased-avatar .vt-avatar:hover {
            z-index: 10;
            transform: scale(1.1);
        }
        .vt-users-purchased-avatar .vt-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            border-radius: inherit;
        }
        .vt-users-purchased-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .vt-users-purchased-list .vt-user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background: #fff;
        }
        .vt-users-purchased-list .vt-avatar {
            flex-shrink: 0;
            border-radius: 50%;
            overflow: hidden;
        }
        .vt-users-purchased-list .vt-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }
        .vt-user-info {
            flex-grow: 1;
        }
        .vt-user-name {
            font-weight: 600;
            margin: 0;
        }
        .vt-user-meta {
            font-size: 14px;
            color: #666;
            margin: 0;
        }
        .vt-user-email {
            cursor: help;
        }
        </style>
        <?php
    }
    
    /**
     * Render avatar view
     */
    private function render_avatar_view($users, $settings) {
        $reverse_stacking = isset($settings['reverse_stacking']) && $settings['reverse_stacking'] === 'yes';
        $container_class = 'vt-users-purchased-avatar';
        if ($reverse_stacking) {
            $container_class .= ' vt-reverse-stacking';
        }
        ?>
        <div class="<?php echo esc_attr($container_class); ?>">
            <?php 
            $user_count = count($users);
            foreach ($users as $index => $user): 
                $z_index = $reverse_stacking ? ($user_count - $index) : ($index + 1);
            ?>
                <div class="vt-avatar" title="<?php echo esc_attr($user['display_name']); ?>" style="z-index: <?php echo esc_attr($z_index); ?>;">
                    <img src="<?php echo esc_url($user['avatar_url']); ?>" alt="<?php echo esc_attr($user['display_name']); ?>" />
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    /**
     * Render list view
     */
    private function render_list_view($users, $settings) {
        ?>
        <div class="vt-users-purchased-list">
            <?php foreach ($users as $user): ?>
                <div class="vt-user-item">
                    <?php if ($settings['show_avatar_in_list'] === 'yes'): ?>
                        <div class="vt-avatar">
                            <img src="<?php echo esc_url($user['avatar_url']); ?>" alt="<?php echo esc_attr($user['display_name']); ?>" />
                        </div>
                    <?php endif; ?>
                    
                    <div class="vt-user-info">
                        <?php if ($settings['show_display_name'] === 'yes'): ?>
                            <div class="vt-user-name"><?php echo esc_html($user['display_name']); ?></div>
                        <?php endif; ?>
                        
                        <div class="vt-user-meta">
                            <?php if ($settings['show_email'] === 'yes'): ?>
                                <?php 
                                $email = $user['email'];
                                $max_length = !empty($settings['email_max_length']) ? $settings['email_max_length'] : 20;
                                $display_email = strlen($email) > $max_length ? substr($email, 0, $max_length) . '...' : $email;
                                ?>
                                <div class="vt-user-email" title="<?php echo esc_attr($email); ?>"><?php echo esc_html($display_email); ?></div>
                            <?php endif; ?>
                            
                            <?php if ($settings['show_purchase_date'] === 'yes'): ?>
                                <div class="vt-purchase-date"><?php echo esc_html(date('M j, Y', strtotime($user['purchase_date']))); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
}