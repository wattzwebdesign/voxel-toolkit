<?php
/**
 * Reading Time Widget
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Reading_Time_Widget {
    
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
        add_shortcode('voxel_reading_time', array($this, 'render_shortcode'));
        
        // Enqueue styles
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
    }
    
    /**
     * Register Elementor widget
     */
    public function register_elementor_widget($widgets_manager) {
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/elementor/widgets/reading-time.php';
        $widgets_manager->register(new \Voxel_Toolkit_Elementor_Reading_Time());
    }
    
    /**
     * Calculate reading time
     */
    public static function calculate_reading_time($post_id = null, $words_per_minute = 300) {
        if (!$post_id) {
            $post_id = get_the_ID();
        }
        
        if (!$post_id) {
            return 0;
        }
        
        $post = get_post($post_id);
        if (!$post) {
            return 0;
        }
        
        // Get the content
        $content = $post->post_content;
        
        // Strip shortcodes and HTML
        $content = strip_shortcodes($content);
        $content = wp_strip_all_tags($content);
        
        // Count words
        $word_count = str_word_count($content);
        
        // Calculate reading time
        $reading_time = ceil($word_count / $words_per_minute);
        
        return max(1, $reading_time); // Minimum 1 minute
    }
    
    /**
     * Render reading time
     */
    public static function render_reading_time($args = array()) {
        $defaults = array(
            'post_id' => null,
            'prefix' => 'Reading time: ',
            'postfix' => ' min',
            'words_per_minute' => 300,
            'alignment' => 'left',
            'prefix_color' => '#333333',
            'time_color' => '#333333',
            'postfix_color' => '#333333',
            'prefix_typography' => array(),
            'time_typography' => array(),
            'postfix_typography' => array(),
            'container_class' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $reading_time = self::calculate_reading_time($args['post_id'], $args['words_per_minute']);
        
        $alignment_class = 'voxel-reading-time-' . $args['alignment'];
        $container_classes = 'voxel-reading-time ' . $alignment_class;
        if (!empty($args['container_class'])) {
            $container_classes .= ' ' . $args['container_class'];
        }
        
        ob_start();
        ?>
        <div class="<?php echo esc_attr($container_classes); ?>">
            <?php if (!empty($args['prefix'])) : ?>
                <span class="voxel-reading-time-prefix" style="color: <?php echo esc_attr($args['prefix_color']); ?>">
                    <?php echo esc_html($args['prefix']); ?>
                </span>
            <?php endif; ?>
            
            <span class="voxel-reading-time-value" style="color: <?php echo esc_attr($args['time_color']); ?>">
                <?php echo esc_html($reading_time); ?>
            </span>
            
            <?php if (!empty($args['postfix'])) : ?>
                <span class="voxel-reading-time-postfix" style="color: <?php echo esc_attr($args['postfix_color']); ?>">
                    <?php echo esc_html($args['postfix']); ?>
                </span>
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
            'prefix' => 'Reading time: ',
            'postfix' => ' min',
            'words_per_minute' => 300,
            'alignment' => 'left'
        ), $atts);
        
        return self::render_reading_time($atts);
    }
    
    /**
     * Enqueue styles
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            'voxel-reading-time',
            VOXEL_TOOLKIT_PLUGIN_URL . 'assets/css/reading-time.css',
            array(),
            VOXEL_TOOLKIT_VERSION
        );
    }
}