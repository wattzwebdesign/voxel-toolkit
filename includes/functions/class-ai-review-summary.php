<?php
/**
 * AI Review Summary functionality
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_AI_Review_Summary {
    
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
        add_shortcode('review_summary', array($this, 'review_summary_shortcode'));
        add_shortcode('category_opinions', array($this, 'category_opinions_shortcode'));
        add_action('admin_post_voxel_toolkit_refresh_ai_cache', array($this, 'handle_refresh_cache'));
    }
    
    /**
     * Review summary shortcode: [review_summary post_id=""]
     */
    public function review_summary_shortcode($atts) {
        global $wpdb, $post;
        
        $atts = shortcode_atts(array(
            'post_id' => '',
        ), $atts, 'review_summary');
        
        // Determine the target post.
        $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : (isset($post->ID) ? $post->ID : 0);
        if (!$post_id) {
            return '<p>No post specified.</p>';
        }
        
        // Retrieve reviews from the Voxel timeline table.
        $table_name = $wpdb->prefix . 'voxel_timeline';
        $query = $wpdb->prepare(
            "SELECT content FROM $table_name WHERE details LIKE %s AND post_id = %d",
            '%"rating":%',
            $post_id
        );
        $reviews = $wpdb->get_results($query);
        
        if (empty($reviews)) {
            return '<p>No reviews available for this post.</p>';
        }
        
        $current_review_count = count($reviews);
        
        // Check for cached summary.
        $cached_summary = get_post_meta($post_id, '_voxel_toolkit_ai_review_summary', true);
        $cached_count = get_post_meta($post_id, '_voxel_toolkit_ai_review_summary_count', true);
        
        if (!empty($cached_summary) && $cached_count == $current_review_count) {
            $summary = $cached_summary;
        } else {
            // Combine review texts.
            $review_text = '';
            foreach ($reviews as $review) {
                $review_text .= strip_tags($review->content) . "\n";
            }
            
            // Construct prompt for ChatGPT with language support
            $language_name = $this->get_language_name();
            $prompt = "Please provide a concise summary of the following user reviews for a listing. Focus on the main strengths and weaknesses mentioned by users, and provide a brief overview similar to what you might see on TripAdvisor. Respond in {$language_name}.\n\nReviews:\n" . $review_text;
            
            // Get summary from API
            $summary = $this->get_chatgpt_response($prompt, "You are a helpful assistant that summarizes user reviews. Always respond in {$language_name}.");
            
            if (is_wp_error($summary)) {
                return '<p>Error generating summary: ' . esc_html($summary->get_error_message()) . '</p>';
            }
            
            // Remove any leading "Summary:" text from the returned summary.
            $summary = preg_replace('/^Summary:\s*/i', '', $summary);
            
            // Optionally shorten the summary further (limit to 150 words).
            $words = explode(' ', $summary);
            if (count($words) > 150) {
                $summary = implode(' ', array_slice($words, 0, 150)) . '...';
            }
            
            // Cache the summary.
            update_post_meta($post_id, '_voxel_toolkit_ai_review_summary', $summary);
            update_post_meta($post_id, '_voxel_toolkit_ai_review_summary_count', $current_review_count);
        }
        
        // Wrap the summary in a titled container.
        $output = '<div class="review-summary-wrapper">';
        $output .= '<div class="review-summary">' . wpautop(esc_html($summary)) . '</div>';
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Category opinions shortcode: [category_opinions post_id="" categories="Food, Atmosphere, Service, Value"]
     */
    public function category_opinions_shortcode($atts) {
        global $wpdb, $post;
        
        $atts = shortcode_atts(array(
            'post_id' => '',
            'categories' => 'Food, Atmosphere, Service, Value',
        ), $atts, 'category_opinions');
        
        // Determine target post.
        $post_id = !empty($atts['post_id']) ? intval($atts['post_id']) : (isset($post->ID) ? $post->ID : 0);
        if (!$post_id) {
            return '<p>No post specified.</p>';
        }
        
        // Convert the categories attribute into an array.
        $categories = array_map('trim', explode(',', $atts['categories']));
        
        // Retrieve reviews.
        $table_name = $wpdb->prefix . 'voxel_timeline';
        $query = $wpdb->prepare(
            "SELECT content FROM $table_name WHERE details LIKE %s AND post_id = %d",
            '%"rating":%',
            $post_id
        );
        $reviews = $wpdb->get_results($query);
        
        if (empty($reviews)) {
            return '<p>No reviews available for this post.</p>';
        }
        
        $current_review_count = count($reviews);
        $opinions = array();
        
        // Loop through each category.
        foreach ($categories as $cat) {
            $meta_key = '_voxel_toolkit_ai_opinion_' . strtolower(preg_replace('/\s+/', '_', $cat));
            $count_key = $meta_key . '_review_count';
            
            $cached_opinion = get_post_meta($post_id, $meta_key, true);
            $cached_count = get_post_meta($post_id, $count_key, true);
            
            if (!empty($cached_opinion) && $cached_count == $current_review_count) {
                $opinions[$cat] = $cached_opinion;
                continue;
            }
            
            // Combine review texts.
            $review_text = '';
            foreach ($reviews as $review) {
                $review_text .= strip_tags($review->content) . "\n";
            }
            
            // Construct prompt for this category with language support
            $language_name = $this->get_language_name();
            $prompt = "Based on the following user reviews for a listing, provide one word that best summarizes the overall opinion about {$cat}. Only output one word (for example, 'Delicious' or 'Mediocre'). Respond in {$language_name}.\n\nReviews:\n" . $review_text;
            
            $opinion = $this->get_chatgpt_response($prompt, "You are a helpful assistant that summarizes opinions in one word. Always respond in {$language_name}.", 0.5);
            
            if (is_wp_error($opinion)) {
                $opinions[$cat] = 'Error';
                continue;
            }
            
            // Remove non-letter characters.
            $opinion = preg_replace('/[^a-zA-Z]/', '', trim($opinion));
            $opinions[$cat] = $opinion ?: 'N/A';
            
            update_post_meta($post_id, $meta_key, $opinions[$cat]);
            update_post_meta($post_id, $count_key, $current_review_count);
        }
        
        // Build output boxes.
        $output = '<div class="voxel-toolkit-category-opinions" style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 5px;">';
        foreach ($categories as $cat) {
            $op = isset($opinions[$cat]) ? $opinions[$cat] : 'N/A';
            $output .= '<div class="category-opinion-box" style="border: 1px solid #ddd; padding: 1rem; text-align: center; display: flex; flex-direction: column; justify-content: center; gap: 2px; border-radius:4px;">';
            $output .= '<h4 style="margin: 0; font-size: 0.875rem; font-weight: 600;">' . esc_html($cat) . '</h4>';
            $output .= '<p style="font-size: 0.775rem; margin: 0;">' . esc_html($op) . '</p>';
            $output .= '</div>';
        }
        $output .= '</div>';
        
        return $output;
    }
    
    /**
     * Get the language name for AI prompts from central AI Settings
     */
    private function get_language_name() {
        if (!class_exists('Voxel_Toolkit_Settings')) {
            return 'English';
        }

        $settings = Voxel_Toolkit_Settings::instance();
        $ai_settings = $settings->get_function_settings('ai_settings', array());
        $language_code = isset($ai_settings['response_language']) ? $ai_settings['response_language'] : 'en';

        $languages = array(
            'en' => 'English',
            'es' => 'Spanish',
            'fr' => 'French',
            'de' => 'German',
            'it' => 'Italian',
            'pt' => 'Portuguese',
            'nl' => 'Dutch',
            'pl' => 'Polish',
            'ru' => 'Russian',
            'uk' => 'Ukrainian',
            'ja' => 'Japanese',
            'ko' => 'Korean',
            'zh' => 'Chinese',
            'ar' => 'Arabic',
            'hi' => 'Hindi',
            'tr' => 'Turkish',
            'vi' => 'Vietnamese',
            'th' => 'Thai',
            'id' => 'Indonesian',
            'ms' => 'Malay',
            'sv' => 'Swedish',
            'da' => 'Danish',
            'no' => 'Norwegian',
            'fi' => 'Finnish',
            'el' => 'Greek',
            'cs' => 'Czech',
            'ro' => 'Romanian',
            'hu' => 'Hungarian',
            'he' => 'Hebrew',
        );

        return isset($languages[$language_code]) ? $languages[$language_code] : 'English';
    }
    
    /**
     * Get response from AI API using central AI Settings
     */
    private function get_chatgpt_response($prompt, $system_message = 'You are a helpful assistant.', $temperature = 0.7) {
        // Use central AI Settings
        $ai_settings = Voxel_Toolkit_AI_Settings::instance();

        if (!$ai_settings->is_configured()) {
            return new WP_Error('no_api_key', 'AI API key is not configured. Please set it in Voxel Toolkit > AI Settings.');
        }

        return $ai_settings->generate_completion($prompt, 500, $temperature, $system_message);
    }
    
    /**
     * Handle cache refresh
     */
    public function handle_refresh_cache() {
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized user');
        }
        
        if (!isset($_POST['voxel_toolkit_refresh_ai_cache_nonce']) || !wp_verify_nonce($_POST['voxel_toolkit_refresh_ai_cache_nonce'], 'voxel_toolkit_refresh_ai_cache')) {
            wp_die('Nonce verification failed');
        }
        
        global $wpdb;
        // Delete all Voxel Toolkit AI-related post meta
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_voxel_toolkit_ai_%'");
        
        wp_redirect(admin_url('admin.php?page=voxel-toolkit-settings&ai_cache_refreshed=1'));
        exit;
    }
}