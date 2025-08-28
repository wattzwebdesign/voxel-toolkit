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
            
            // Construct prompt for ChatGPT.
            $prompt = "Please provide a concise summary of the following user reviews for a listing. Focus on the main strengths and weaknesses mentioned by users, and provide a brief overview similar to what you might see on TripAdvisor.\n\nReviews:\n" . $review_text;
            
            // Get summary from API
            $summary = $this->get_chatgpt_response($prompt, 'You are a helpful assistant that summarizes user reviews.');
            
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
            
            // Construct prompt for this category.
            $prompt = "Based on the following user reviews for a listing, provide one word that best summarizes the overall opinion about {$cat}. Only output one word (for example, 'Delicious' or 'Mediocre').\n\nReviews:\n" . $review_text;
            
            $opinion = $this->get_chatgpt_response($prompt, 'You are a helpful assistant that summarizes opinions in one word.', 0.5);
            
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
     * Get response from ChatGPT API
     */
    private function get_chatgpt_response($prompt, $system_message = 'You are a helpful assistant.', $temperature = 0.7) {
        // Get settings
        $settings = get_option('voxel_toolkit_options', array());
        $ai_settings = isset($settings['ai_review_summary']) ? $settings['ai_review_summary'] : array();
        $api_key = isset($ai_settings['api_key']) ? $ai_settings['api_key'] : '';
        
        if (empty($api_key)) {
            return new WP_Error('no_api_key', 'ChatGPT API key is not configured. Please set it in the Voxel Toolkit settings.');
        }
        
        $request_body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array('role' => 'system', 'content' => $system_message),
                array('role' => 'user', 'content' => $prompt),
            ),
            'temperature' => $temperature,
        );
        
        $args = array(
            'headers' => array(
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ),
            'body' => json_encode($request_body),
            'timeout' => 15,
        );
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', $args);
        
        if (is_wp_error($response)) {
            return new WP_Error('api_error', 'Error contacting ChatGPT API: ' . $response->get_error_message());
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (empty($data) || !isset($data['choices'][0]['message']['content'])) {
            return new WP_Error('unexpected_response', 'Unexpected response from ChatGPT API.');
        }
        
        return $data['choices'][0]['message']['content'];
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