<?php
/**
 * Profile Progress Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Profile_Progress_Widget {
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Register Elementor widget
        add_action('elementor/widgets/register', array($this, 'register_elementor_widget'));
        
        // Add shortcode
        add_shortcode('voxel_profile_progress', array($this, 'render_shortcode'));
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/widgets/profile-progress.php';
        $widgets_manager->register(new \Voxel_Toolkit_Elementor_Profile_Progress());
    }
    
    /**
     * Get profile fields from voxel:post_types option
     */
    public static function get_available_profile_fields() {
        $post_types = get_option('voxel:post_types', array());

        // Handle serialized data
        if (is_string($post_types)) {
            $post_types = maybe_unserialize($post_types);
        }

        // Try JSON decode if it's a JSON string
        if (is_string($post_types)) {
            $decoded = json_decode($post_types, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $post_types = $decoded;
            }
        }

        if (empty($post_types) || !is_array($post_types)) {
            return array('' => __('No profile fields found', 'voxel-toolkit'));
        }

        // Look for 'profile' post type
        if (!isset($post_types['profile']) || !is_array($post_types['profile'])) {
            return array('' => __('Profile post type not found', 'voxel-toolkit'));
        }

        $profile_data = $post_types['profile'];

        // Get fields array
        if (!isset($profile_data['fields']) || !is_array($profile_data['fields'])) {
            return array('' => __('No profile fields found', 'voxel-toolkit'));
        }

        $available_fields = array();

        // Loop through fields and extract key and label
        foreach ($profile_data['fields'] as $field) {
            if (isset($field['key']) && !empty($field['key'])) {
                // Skip ui-step fields as they're not data fields
                if (isset($field['type']) && $field['type'] === 'ui-step') {
                    continue;
                }

                $label = isset($field['label']) && !empty($field['label'])
                    ? $field['label']
                    : $field['key'];

                $available_fields[$field['key']] = $label;
            }
        }

        if (empty($available_fields)) {
            return array('' => __('No profile fields found', 'voxel-toolkit'));
        }

        return $available_fields;
    }

    /**
     * Get user profile field data
     */
    public static function get_user_profile_fields($user_id, $field_keys) {
        global $wpdb;
        
        if (!$user_id || empty($field_keys)) {
            return [];
        }
        
        // Step 1: Get user's profile ID from wp_usermeta
        $profile_id = get_user_meta($user_id, 'voxel:profile_id', true);
        
        if (!$profile_id) {
            return [];
        }
        
        $field_data = [];
        
        foreach ($field_keys as $field_key) {
            // Special case: voxel:avatar is stored in wp_usermeta, not wp_postmeta
            if ($field_key === 'voxel:avatar') {
                $meta_value = get_user_meta($user_id, 'voxel:avatar', true);
            } else {
                // Step 2: Check if field exists in wp_postmeta using the profile_id as post_id
                $meta_value = get_post_meta($profile_id, $field_key, true);
            }
            
            // More comprehensive check for field completion
            $is_completed = false;
            
            if ($meta_value !== '' && $meta_value !== false && $meta_value !== null) {
                // For arrays/objects, check if they have meaningful content
                if (is_array($meta_value) || is_object($meta_value)) {
                    $is_completed = !empty($meta_value);
                } 
                // For strings, check if not just whitespace
                elseif (is_string($meta_value)) {
                    $is_completed = trim($meta_value) !== '';
                }
                // For numbers, including 0
                elseif (is_numeric($meta_value)) {
                    $is_completed = true;
                }
                // For other types
                else {
                    $is_completed = !empty($meta_value);
                }
            }
            
            $field_data[$field_key] = [
                'exists' => $is_completed,
                'value' => $meta_value
            ];
        }
        
        return $field_data;
    }
    
    /**
     * Calculate progress percentage
     */
    public static function calculate_progress($field_data) {
        if (empty($field_data)) {
            return 0;
        }
        
        $total_fields = count($field_data);
        $completed_fields = 0;
        
        foreach ($field_data as $field) {
            if ($field['exists']) {
                $completed_fields++;
            }
        }
        
        return round(($completed_fields / $total_fields) * 100);
    }
    
    /**
     * Render profile progress
     */
    public static function render_profile_progress($args = array()) {
        $defaults = array(
            'user_id' => null,
            'field_keys' => array(),
            'progress_type' => 'horizontal',
            'show_percentage' => true,
            'show_field_list' => false,
            'container_class' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        if (!$args['user_id']) {
            $args['user_id'] = get_current_user_id();
        }
        
        if (!$args['user_id'] || empty($args['field_keys'])) {
            return '';
        }
        
        $field_data = self::get_user_profile_fields($args['user_id'], $args['field_keys']);
        $progress_percentage = self::calculate_progress($field_data);
        
        $container_classes = 'voxel-profile-progress';
        if (!empty($args['container_class'])) {
            $container_classes .= ' ' . $args['container_class'];
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($container_classes); ?>">
            <?php if ($args['progress_type'] === 'horizontal') : ?>
                <div class="voxel-progress-bar">
                    <div class="voxel-progress-fill" style="width: <?php echo esc_attr($progress_percentage); ?>%;"></div>
                </div>
            <?php else : ?>
                <div class="voxel-circular-progress">
                    <svg viewBox="0 0 120 120">
                        <circle class="circle-bg" cx="60" cy="60" r="54" fill="none" />
                        <circle class="circle-progress" cx="60" cy="60" r="54" fill="none" 
                                stroke-dasharray="<?php echo esc_attr(2 * pi() * 54); ?>" 
                                stroke-dashoffset="<?php echo esc_attr(2 * pi() * 54 * (1 - $progress_percentage / 100)); ?>" />
                    </svg>
                </div>
            <?php endif; ?>
            
            <?php if ($args['show_percentage']) : ?>
                <div class="voxel-progress-percentage"><?php echo esc_html($progress_percentage); ?>%</div>
            <?php endif; ?>
            
            <?php if ($args['show_field_list'] && !empty($args['field_keys'])) : ?>
                <div class="voxel-field-list">
                    <?php foreach ($args['field_keys'] as $field_key) : 
                        $is_completed = isset($field_data[$field_key]) && $field_data[$field_key]['exists'];
                        $status_class = $is_completed ? 'completed' : 'incomplete';
                        $status_icon = $is_completed ? '✓' : '✗';
                    ?>
                        <div class="voxel-field-item <?php echo esc_attr($status_class); ?>">
                            <span class="field-icon"><?php echo esc_html($status_icon); ?></span>
                            <span class="field-label"><?php echo esc_html($field_key); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
        
        return ob_get_clean();
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'user_id' => '',
            'field_keys' => '',
            'progress_type' => 'horizontal',
            'show_percentage' => 'yes',
            'show_field_list' => 'no'
        ), $atts);
        
        // Parse field keys
        $field_keys = array();
        if (!empty($atts['field_keys'])) {
            $field_keys = array_map('trim', explode(',', $atts['field_keys']));
        }
        
        // Convert string values to boolean
        $show_percentage = $atts['show_percentage'] === 'yes';
        $show_field_list = $atts['show_field_list'] === 'yes';
        
        // Parse user ID
        $user_id = !empty($atts['user_id']) ? intval($atts['user_id']) : null;
        
        return self::render_profile_progress(array(
            'user_id' => $user_id,
            'field_keys' => $field_keys,
            'progress_type' => $atts['progress_type'],
            'show_percentage' => $show_percentage,
            'show_field_list' => $show_field_list
        ));
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'voxel-profile-progress',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/profile-progress.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
}