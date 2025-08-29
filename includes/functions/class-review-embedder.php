<?php
/**
 * Voxel Review Embedder Function
 *
 * @package Voxel_Toolkit
 * @version 2.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Review_Embedder {
    
    private $settings_manager;
    private $function_enabled = false;
    
    public function __construct() {
        // Get settings manager instance
        require_once VOXEL_TOOLKIT_PLUGIN_DIR . 'includes/class-settings.php';
        $this->settings_manager = Voxel_Toolkit_Settings::instance();
        
        $this->function_enabled = $this->settings_manager->is_function_enabled('review_embedder');
        
        if ($this->function_enabled) {
            $this->init();
        }
    }
    
    /**
     * Initialize the function
     */
    private function init() {
        // Register shortcode
        add_shortcode('voxel_review_badge', array($this, 'render_shortcode'));
        
        // AJAX endpoints for iframe
        add_action('wp_ajax_voxel_review_iframe', array($this, 'handle_iframe_request'));
        add_action('wp_ajax_nopriv_voxel_review_iframe', array($this, 'handle_iframe_request'));
        
        // Add styles to frontend
        add_action('wp_head', array($this, 'add_inline_styles'));
        
        // Flush rewrite rules to clear any old rules that might conflict
        add_action('init', array($this, 'maybe_flush_rewrite_rules'));
        
        // Early header override for iframe requests
        add_action('init', array($this, 'check_iframe_request'), 5);
    }
    
    /**
     * Check if this is an iframe request and set headers early
     */
    public function check_iframe_request() {
        if (isset($_GET['action']) && $_GET['action'] === 'voxel_review_iframe') {
            // Remove WordPress security headers early
            remove_action('send_headers', 'wp_app_ssl_headers');
            remove_action('send_headers', 'wp_default_headers');
            
            // Set permissive headers early
            add_action('send_headers', array($this, 'set_iframe_headers'), 1);
        }
    }
    
    /**
     * Set iframe headers early to prevent conflicts
     */
    public function set_iframe_headers() {
        header_remove('X-Frame-Options');
        header('X-Frame-Options: ALLOWALL', true);
        header('Content-Security-Policy: frame-ancestors *', true);
    }
    
    /**
     * Maybe flush rewrite rules to clear old conflicting rules
     */
    public function maybe_flush_rewrite_rules() {
        if (get_option('voxel_toolkit_review_embedder_flushed') !== '1') {
            flush_rewrite_rules();
            update_option('voxel_toolkit_review_embedder_flushed', '1');
        }
    }
    
    /**
     * Add inline styles to head
     */
    public function add_inline_styles() {
        if (!is_admin()) {
            echo '<style>' . $this->get_css() . '</style>';
        }
    }
    
    /**
     * Render shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'post_id' => get_the_ID(),
            'show_embed' => 'false'
        ), $atts, 'voxel_review_badge');
        
        $post_id = intval($atts['post_id']);
        
        if (!$post_id) {
            return '<!-- Review Badge: No post ID -->';
        }
        
        // Get review data
        $review_data = $this->get_review_data($post_id);
        
        // Build output
        $output = $this->build_badge_html($review_data, $post_id);
        
        // Add embed code if requested
        if ($atts['show_embed'] === 'true') {
            $output .= $this->build_embed_section($post_id);
        }
        
        return $output;
    }
    
    /**
     * Build badge HTML
     */
    private function build_badge_html($review_data, $post_id) {
        $settings = $this->get_settings();
        
        $html = '<div class="voxel-review-badge-container">';
        
        if (!$review_data || $review_data['count'] == 0) {
            $html .= '<div class="voxel-review-badge no-reviews">No reviews yet</div>';
        } else {
            $html .= '<div class="voxel-review-badge">';
            
            // Logo
            if (!empty($settings['logo_url'])) {
                $html .= '<span class="voxel-review-logo">';
                $html .= '<img src="' . esc_url($settings['logo_url']) . '" alt="' . esc_attr($settings['logo_alt']) . '" />';
                $html .= '</span>';
            }
            
            // Rating number
            $html .= '<span class="voxel-review-rating">' . number_format($review_data['average'], 1) . '</span>';
            
            // Stars
            $html .= '<span class="voxel-review-stars">' . $this->generate_stars($review_data['average']) . '</span>';
            
            // Review count with link
            if ($settings['show_count']) {
                $text = $review_data['count'] == 1 ? 'review' : 'reviews';
                $post_url = get_permalink($post_id);
                $html .= '<a href="' . esc_url($post_url) . '" class="voxel-review-count" target="_blank">';
                $html .= sprintf('%s %s', number_format($review_data['count']), $text);
                $html .= '</a>';
            }
            
            $html .= '</div>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Build embed section
     */
    private function build_embed_section($post_id) {
        // Force fresh URL generation - no caching
        $site_url = get_site_url();
        $iframe_url = $site_url . '/wp-admin/admin-ajax.php?action=voxel_review_iframe&post_id=' . $post_id;
        $embed_code = '<iframe src="' . esc_url($iframe_url) . '" width="350" height="80" frameborder="0" style="border: none; overflow: hidden;"></iframe>';
        
        $html = '<div class="voxel-review-embed-section">';
        $html .= '<h4>Embed this review badge on your website:</h4>';
        $html .= '<button type="button" class="voxel-embed-toggle" onclick="javascript:var el=this.nextElementSibling;el.style.display=el.style.display===\'none\'?\'block\':\'none\';">Get Embed Code</button>';
        $html .= '<div class="voxel-embed-code-wrapper" style="display:none;">';
        $html .= '<div style="margin-top: 15px; padding: 15px; background: #f0f8ff; border: 1px solid #0073aa; border-radius: 6px;">';
        $html .= '<h5 style="margin-top: 0; color: #0073aa;">📋 Copy this embed code:</h5>';
        $html .= '<input type="text" value="' . htmlspecialchars($embed_code, ENT_QUOTES | ENT_HTML401, 'UTF-8', false) . '" readonly onclick="this.select();" style="width: 100%; padding: 8px; font-family: monospace; font-size: 12px; border: 1px solid #ddd; border-radius: 4px;" />';
        $html .= '<button type="button" class="voxel-copy-code" onclick="javascript:navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent=\'✅ Copied!\';setTimeout(()=>this.textContent=\'📋 Copy Code\',2000);" style="margin-top: 10px;">📋 Copy Code</button>';
        $html .= '<p style="margin: 10px 0 0 0; font-size: 12px; color: #666;"><strong>Note:</strong> Paste this code on any website where you want to display your review badge.</p>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Handle iframe AJAX request
     */
    public function handle_iframe_request() {
        $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
        
        if (!$post_id) {
            wp_die('Invalid post ID');
        }
        
        // Get review data
        $review_data = $this->get_review_data($post_id);
        $settings = $this->get_settings();
        
        // Prevent any output buffering that might interfere with headers
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Set headers for iframe embedding - use most permissive options
        header('Content-Type: text/html; charset=utf-8');
        header_remove('X-Frame-Options'); // Remove any existing headers
        header('X-Frame-Options: ALLOWALL', true); // Replace any existing header
        header('Content-Security-Policy: frame-ancestors *', true);
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
        
        // Remove WordPress default security headers for this endpoint
        remove_action('send_headers', 'wp_app_ssl_headers');
        remove_action('send_headers', 'wp_default_headers');
        
        ?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Badge</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            padding: 10px;
            background: transparent;
        }
        <?php echo $this->get_css(); ?>
    </style>
</head>
<body>
    <?php echo $this->build_badge_html($review_data, $post_id); ?>
</body>
</html><?php
        
        wp_die();
    }
    
    /**
     * Get review data from database
     */
    private function get_review_data($post_id) {
        global $wpdb;
        
        // Cache key
        $cache_key = 'voxel_review_data_' . $post_id;
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        // Get review data from correct meta key
        $meta_value = get_post_meta($post_id, 'voxel:review_stats', true);
        
        if (!empty($meta_value)) {
            // Parse JSON data
            $review_data = is_string($meta_value) ? json_decode($meta_value, true) : $meta_value;
            
            if ($review_data && isset($review_data['total']) && isset($review_data['average'])) {
                $average_raw = floatval($review_data['average']);
                $count = intval($review_data['total']);
                
                // Convert from -2 to 2 range to 1 to 5 star range
                // Formula: score + 3
                $average_stars = $average_raw + 3;
                
                // Ensure it's within 1-5 range
                $average_stars = max(1, min(5, $average_stars));
                
                $result = array(
                    'average' => $average_stars,
                    'count' => $count
                );
                
                // Cache for 5 minutes
                set_transient($cache_key, $result, 300);
                return $result;
            }
        }
        
        // Fallback: try alternative meta keys
        $possible_meta_keys = array(
            'voxel:reviews',
            '_voxel_reviews', 
            'reviews',
            'review_data',
            'voxel_reviews'
        );
        
        foreach ($possible_meta_keys as $meta_key) {
            $meta_value = get_post_meta($post_id, $meta_key, true);
            
            if (!empty($meta_value)) {
                $review_data = is_string($meta_value) ? json_decode($meta_value, true) : $meta_value;
                if ($review_data && isset($review_data['average'])) {
                    $average_raw = floatval($review_data['average']);
                    $count = isset($review_data['total']) ? intval($review_data['total']) : 0;
                    
                    // Convert from -2 to 2 range to 1 to 5 star range
                    $average_stars = $average_raw + 3;
                    $average_stars = max(1, min(5, $average_stars));
                    
                    $result = array(
                        'average' => $average_stars,
                        'count' => $count
                    );
                    
                    // Cache for 5 minutes
                    set_transient($cache_key, $result, 300);
                    return $result;
                }
            }
        }
        
        // No review data found
        $result = array('average' => 0, 'count' => 0);
        set_transient($cache_key, $result, 300);
        return $result;
    }
    
    /**
     * Generate stars HTML
     */
    private function generate_stars($rating) {
        $html = '';
        $full_stars = floor($rating);
        $has_half = ($rating - $full_stars) >= 0.5;
        
        for ($i = 1; $i <= 5; $i++) {
            if ($i <= $full_stars) {
                $html .= '<span class="star full">★</span>';
            } elseif ($i == $full_stars + 1 && $has_half) {
                $html .= '<span class="star half">★</span>';
            } else {
                $html .= '<span class="star empty">☆</span>';
            }
        }
        
        return $html;
    }
    
    /**
     * Get settings
     */
    private function get_settings() {
        $defaults = array(
            'logo_url' => '',
            'logo_alt' => 'Reviews',
            'show_count' => true,
            'star_color' => '#FFD700'
        );
        
        $saved = $this->settings_manager->get_function_settings('review_embedder', array());
        return array_merge($defaults, $saved);
    }
    
    /**
     * Get CSS
     */
    private function get_css() {
        $settings = $this->get_settings();
        $star_color = $settings['star_color'];
        
        return "
        .voxel-review-badge-container {
            display: inline-block;
            font-size: 14px;
        }
        
        .voxel-review-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 16px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            white-space: nowrap;
        }
        
        .voxel-review-badge.no-reviews {
            color: #999;
            font-style: italic;
        }
        
        .voxel-review-logo img {
            height: 24px;
            width: auto;
            display: block;
        }
        
        .voxel-review-rating {
            font-size: 18px;
            font-weight: 700;
            color: #333;
        }
        
        .voxel-review-stars {
            display: flex;
            gap: 2px;
        }
        
        .voxel-review-stars .star {
            font-size: 16px;
            line-height: 1;
        }
        
        .voxel-review-stars .star.full {
            color: {$star_color};
        }
        
        .voxel-review-stars .star.half {
            color: {$star_color};
            opacity: 0.5;
        }
        
        .voxel-review-stars .star.empty {
            color: #ddd;
        }
        
        .voxel-review-count {
            color: #0073aa;
            font-size: 13px;
            text-decoration: none;
            transition: color 0.2s ease;
        }
        
        .voxel-review-count:hover {
            color: #005a87;
            text-decoration: underline;
        }
        
        .voxel-review-embed-section {
            margin-top: 20px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 6px;
        }
        
        .voxel-embed-toggle {
            background: #0073aa;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        
        .voxel-embed-toggle:hover {
            background: #005a87;
        }
        
        .voxel-embed-code-wrapper {
            margin-top: 15px;
        }
        
        .voxel-embed-code {
            width: 100%;
            height: 80px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-family: monospace;
            font-size: 12px;
            resize: vertical;
        }
        
        .voxel-copy-code {
            margin-top: 10px;
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 13px;
        }
        
        .voxel-copy-code:hover {
            background: #218838;
        }
        ";
    }
    
    /**
     * Render settings
     */
    public function render_settings($settings) {
        $logo_url = isset($settings['logo_url']) ? $settings['logo_url'] : '';
        $logo_alt = isset($settings['logo_alt']) ? $settings['logo_alt'] : 'Reviews';
        $show_count = isset($settings['show_count']) ? $settings['show_count'] : true;
        $star_color = isset($settings['star_color']) ? $settings['star_color'] : '#FFD700';
        ?>
        <tr>
            <th scope="row">
                <label><?php _e('Logo URL', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input type="url" 
                       name="voxel_toolkit_options[review_embedder][logo_url]" 
                       value="<?php echo esc_url($logo_url); ?>" 
                       class="regular-text" 
                       placeholder="https://example.com/logo.png" />
                <p class="description"><?php _e('Logo image URL (TripAdvisor, Yelp, Google, etc.)', 'voxel-toolkit'); ?></p>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('Logo Alt Text', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input type="text" 
                       name="voxel_toolkit_options[review_embedder][logo_alt]" 
                       value="<?php echo esc_attr($logo_alt); ?>" 
                       class="regular-text" />
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('Show Review Count', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <label>
                    <input type="checkbox" 
                           name="voxel_toolkit_options[review_embedder][show_count]" 
                           value="1" 
                           <?php checked($show_count); ?> />
                    <?php _e('Display total number of reviews', 'voxel-toolkit'); ?>
                </label>
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('Star Color', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <input type="color" 
                       name="voxel_toolkit_options[review_embedder][star_color]" 
                       value="<?php echo esc_attr($star_color); ?>" />
            </td>
        </tr>
        
        <tr>
            <th scope="row">
                <label><?php _e('How to Use', 'voxel-toolkit'); ?></label>
            </th>
            <td>
                <div style="background: #f5f5f5; padding: 15px; border-radius: 4px;">
                    <p><strong>Basic Usage:</strong></p>
                    <code style="display: block; margin: 5px 0; padding: 5px; background: white;">[voxel_review_badge]</code>
                    
                    <p style="margin-top: 15px;"><strong>For Specific Post:</strong></p>
                    <code style="display: block; margin: 5px 0; padding: 5px; background: white;">[voxel_review_badge post_id="123"]</code>
                    
                    <p style="margin-top: 15px;"><strong>With Embed Code Generator:</strong></p>
                    <code style="display: block; margin: 5px 0; padding: 5px; background: white;">[voxel_review_badge show_embed="true"]</code>
                    
                    <p style="margin-top: 15px; color: #666;">
                        <strong>Note:</strong> The embed code uses AJAX URLs which work on any WordPress site without special permalinks.
                    </p>
                </div>
            </td>
        </tr>
        <?php
    }
}