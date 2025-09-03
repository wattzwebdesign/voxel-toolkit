<?php
/**
 * Custom Submission Messages Function
 * 
 * Allows customizing confirmation messages shown after post submissions per post type
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Custom_Submission_Messages {
    
    private $settings;
    private $options = array();
    
    /**
     * Constructor
     */
    public function __construct() {
        $this->settings = Voxel_Toolkit_Settings::instance();
        $this->load_settings();
        $this->init_hooks();
    }
    
    /**
     * Load settings
     */
    private function load_settings() {
        $this->options = $this->settings->get_function_settings('custom_submission_messages', array(
            'enabled' => false,
            'post_type_settings' => array()
        ));
        
        // Listen for settings updates
        add_action('voxel_toolkit/settings_updated', array($this, 'on_settings_updated'), 10, 2);
    }
    
    /**
     * Handle settings update
     */
    public function on_settings_updated($old_settings, $new_settings) {
        if (isset($new_settings['custom_submission_messages'])) {
            $this->options = $new_settings['custom_submission_messages'];
        }
    }
    
    /**
     * Initialize hooks
     */
    private function init_hooks() {
        // Hook into text replacement filters with high priority
        add_filter('gettext', array($this, 'filter_submission_message'), 1, 3);
        add_filter('gettext_with_context', array($this, 'filter_submission_message_with_context'), 1, 4);
        
        // Add JavaScript for frontend message replacement
        add_action('wp_footer', array($this, 'add_frontend_script'));
        
        // Hook into AJAX responses for better coverage
        add_filter('wp_die_ajax_handler', array($this, 'capture_ajax_response'));
    }
    
    /**
     * Get custom message for current post type being submitted
     */
    private function get_custom_message_for_current_submission() {
        // Try to determine the post type from various sources
        $post_type = $this->get_current_post_type();
        
        if (!$post_type || empty($this->options['post_type_settings'][$post_type])) {
            return null;
        }
        
        $post_settings = $this->options['post_type_settings'][$post_type];
        
        // Check if custom messages are enabled for this post type
        if (empty($post_settings['enabled'])) {
            return null;
        }
        
        // Determine message type based on user status and post status
        $message_type = $this->determine_message_type($post_type);
        
        if (!$message_type || empty($post_settings['messages'][$message_type])) {
            return null;
        }
        
        return $post_settings['messages'][$message_type];
    }
    
    /**
     * Determine which message type to show based on user status and post handling
     */
    private function determine_message_type($post_type) {
        // Check if user is pre-approved and pre-approve posts function is active
        if ($this->should_use_pre_approved_message()) {
            return 'pre_approved';
        }
        
        // For now, we'll determine based on whether post will be published or pending
        // In the future, we could check actual post status after creation
        if ($this->will_post_be_published()) {
            return 'published';
        }
        
        return 'pending_review';
    }
    
    /**
     * Check if we should use the pre-approved message
     */
    private function should_use_pre_approved_message() {
        // Check if pre-approved posts function is active and configured
        $settings = Voxel_Toolkit_Settings::instance();
        
        if (!$settings->is_function_enabled('pre_approve_posts')) {
            return false;
        }
        
        // Get pre-approved posts settings
        $pre_approve_settings = $settings->get_function_settings('pre_approve_posts');
        
        // Check if current user is pre-approved
        $user_id = get_current_user_id();
        if (!$user_id) {
            return false;
        }
        
        // Check if user is pre-approved by any method
        return $this->is_user_pre_approved($user_id, $pre_approve_settings);
    }
    
    /**
     * Determine if the post will be published immediately or go to pending
     */
    private function will_post_be_published() {
        // Check if user is pre-approved (would be published via pre-approve function)
        if ($this->should_use_pre_approved_message()) {
            return true;
        }
        
        // In Voxel, posts typically go to pending review regardless of user role
        // The actual post status should determine the message, not user capabilities
        // This method should return false for most cases to show pending_review message
        // The frontend JavaScript will show the correct message after submission
        
        return false;
    }
    
    
    /**
     * Check if user is pre-approved (using same logic as pre-approve posts function)
     */
    private function is_user_pre_approved($user_id, $pre_approve_settings) {
        // Check if verified profile approval is enabled and user is verified
        if (!empty($pre_approve_settings['approve_verified'])) {
            if ($this->is_user_verified($user_id)) {
                return true;
            }
        }
        
        // Check role-based approval
        if (!empty($pre_approve_settings['approved_roles'])) {
            $user = get_user_by('ID', $user_id);
            if ($user) {
                foreach ($pre_approve_settings['approved_roles'] as $role) {
                    if (in_array($role, $user->roles)) {
                        return true;
                    }
                }
            }
        }
        
        // Check manual pre-approval
        $manually_approved = get_user_meta($user_id, 'voxel_toolkit_pre_approved', true);
        return $manually_approved === 'yes';
    }
    
    /**
     * Check if user has a verified profile (using same logic as pre-approve posts)
     */
    private function is_user_verified($user_id) {
        // Get user's profile post ID from user meta
        $profile_post_id = get_user_meta($user_id, 'voxel:profile_id', true);
        
        if (!$profile_post_id) {
            return false;
        }
        
        // Check if profile has verified status in post meta
        $verified = get_post_meta($profile_post_id, 'voxel:verified', true);
        
        return ($verified === '1' || $verified === 1);
    }
    
    /**
     * Determine current post type being submitted
     */
    private function get_current_post_type() {
        // Check AJAX request
        if (wp_doing_ajax()) {
            // Check common AJAX parameters
            if (isset($_POST['post_type'])) {
                return sanitize_text_field($_POST['post_type']);
            }
            
            if (isset($_REQUEST['post_type'])) {
                return sanitize_text_field($_REQUEST['post_type']);
            }
            
            // Check Voxel-specific parameters
            if (isset($_POST['template'])) {
                return sanitize_text_field($_POST['template']);
            }
        }
        
        // Check URL parameters
        if (isset($_GET['post_type'])) {
            return sanitize_text_field($_GET['post_type']);
        }
        
        // Check if we're on a create post page
        global $wp;
        $current_url = home_url($wp->request);
        
        // Try to extract post type from URL patterns like /create-listing/, /create-event/, etc.
        if (preg_match('/\/create-([^\/]+)\/?/', $current_url, $matches)) {
            // Convert URL slug to post type (create-listing -> listing)
            $post_type_slug = $matches[1];
            
            // Check if this matches any registered post type
            $post_types = get_post_types(array('public' => true), 'names');
            if (in_array($post_type_slug, $post_types)) {
                return $post_type_slug;
            }
            
            // Try common variations
            $variations = array(
                'listing' => 'listing',
                'event' => 'event', 
                'job' => 'job',
                'place' => 'place'
            );
            
            if (isset($variations[$post_type_slug])) {
                return $variations[$post_type_slug];
            }
        }
        
        return null;
    }
    
    /**
     * Quick check if text might be a submission message
     */
    private function looks_like_submission_message($text) {
        $text_lower = strtolower($text);
        return (strpos($text_lower, 'submitted') !== false && strpos($text_lower, 'review') !== false) ||
               (strpos($text_lower, 'post') !== false && strpos($text_lower, 'published') !== false) ||
               (strpos($text_lower, 'thank you') !== false && strpos($text_lower, 'submission') !== false);
    }
    
    /**
     * Filter submission messages (simple gettext)
     */
    public function filter_submission_message($translated, $text, $domain) {
        // Early return if not a submission-related text
        if (!$this->looks_like_submission_message($text) && !$this->looks_like_submission_message($translated)) {
            return $translated;
        }
        
        // Only process if we have a custom message to show
        $custom_message = $this->get_custom_message_for_current_submission();
        if (!$custom_message) {
            return $translated;
        }
        
        // Only replace specific complete submission messages
        $submission_messages = array(
            'Your post has been submitted for review.',
            'Your post has been submitted for review',
            'Post submitted for review.',
            'Post submitted for review',
            'Your listing has been submitted for review.',
            'Your listing has been submitted for review',
            'Your event has been submitted for review.',
            'Your event has been submitted for review',
            'Thank you for your submission.',
            'Your submission has been received.',
            'Post submitted successfully.'
        );
        
        foreach ($submission_messages as $submission_message) {
            if ($text === $submission_message || $translated === $submission_message) {
                return $custom_message;
            }
        }
        
        return $translated;
    }
    
    /**
     * Filter submission messages with context
     */
    public function filter_submission_message_with_context($translated, $text, $context, $domain) {
        // Early return if not submission-related context or text
        if ($context !== 'create post' && $context !== 'post submission') {
            if (!$this->looks_like_submission_message($text) && !$this->looks_like_submission_message($translated)) {
                return $translated;
            }
        }
        
        // Only process if we have a custom message to show
        $custom_message = $this->get_custom_message_for_current_submission();
        if (!$custom_message) {
            return $translated;
        }
        
        // Handle context-specific translations - only for specific submission contexts
        if ($context === 'create post' || $context === 'post submission') {
            // Only replace complete submission messages in submission contexts
            $submission_messages = array(
                'submitted for review',
                'has been submitted for review',
                'Your post has been submitted for review',
                'Post submitted successfully',
                'Thank you for your submission',
                'Your submission has been received'
            );
            
            foreach ($submission_messages as $submission_message) {
                if ($text === $submission_message || $translated === $submission_message ||
                    stripos($text, $submission_message) !== false || stripos($translated, $submission_message) !== false) {
                    return $custom_message;
                }
            }
        }
        
        return $translated;
    }
    
    /**
     * Add frontend script for message replacement
     */
    public function add_frontend_script() {
        // Only add script if we have custom messages configured
        if (empty($this->options['post_type_settings'])) {
            return;
        }
        
        $post_type = $this->get_current_post_type();
        if (!$post_type || empty($this->options['post_type_settings'][$post_type]['enabled'])) {
            return;
        }
        
        $custom_message = $this->get_custom_message_for_current_submission();
        if (empty($custom_message)) {
            return;
        }
        
        ?>
        <style type="text/css">
        /* Ensure custom messages inherit proper styling */
        .vx-infobox .voxel-custom-message,
        .ts-notice .voxel-custom-message,
        .ts-form-status .voxel-custom-message,
        .message .voxel-custom-message,
        .notice .voxel-custom-message,
        [class*="success"] .voxel-custom-message,
        [class*="message"] .voxel-custom-message {
            font-size: inherit;
            color: inherit;
            font-family: inherit;
            font-weight: inherit;
            line-height: inherit;
            margin: inherit;
            padding: inherit;
        }
        </style>
        <script type="text/javascript">
        (function() {
            console.log('Voxel Toolkit: Custom submission messages loaded for post type: <?php echo esc_js($post_type); ?>');
            
            var customMessage = <?php echo json_encode($custom_message); ?>;
            console.log('Voxel Toolkit: Custom message to use:', customMessage);
            
            // Function to replace submission messages
            function replaceSubmissionMessages() {
                // Indicators that suggest this is a submission message
                var submissionIndicators = [
                    'submitted', 'review', 'pending', 'success', 'thank', 'received',
                    'approval', 'publish', 'post has been', 'listing has been', 'event has been'
                ];
                
                // Target common Voxel and WordPress message containers
                var selectors = [
                    '.vx-infobox', '.ts-notice', '.ts-form-status', '.message', '.notice',
                    '[class*="success"]', '[class*="message"]', '[class*="notification"]',
                    '.form-response', '.ajax-response', '.vx-success-message',
                    '.post-submit-message', '.submission-message'
                ];
                
                // Replace content in specific elements while preserving structure
                selectors.forEach(function(selector) {
                    var elements = document.querySelectorAll(selector);
                    elements.forEach(function(element) {
                        var elementText = element.textContent.toLowerCase();
                        var isSubmissionMessage = submissionIndicators.some(function(indicator) {
                            return elementText.includes(indicator.toLowerCase());
                        });
                        
                        if (isSubmissionMessage && !element.hasAttribute('data-voxel-replaced')) {
                            console.log('Voxel Toolkit: Replacing message in:', selector);
                            element.setAttribute('data-voxel-replaced', 'true');
                            
                            // Try to preserve styling by only replacing text content
                            // Check if element has child elements (complex structure)
                            if (element.children.length > 0) {
                                // Has child elements - find and replace text nodes only
                                var textNodes = [];
                                var walker = document.createTreeWalker(
                                    element,
                                    NodeFilter.SHOW_TEXT,
                                    null,
                                    false
                                );
                                var node;
                                while (node = walker.nextNode()) {
                                    if (node.textContent.trim().length > 5) {
                                        textNodes.push(node);
                                    }
                                }
                                
                                // Replace the longest text node (likely the main message)
                                if (textNodes.length > 0) {
                                    var longestNode = textNodes.reduce(function(prev, current) {
                                        return (current.textContent.length > prev.textContent.length) ? current : prev;
                                    });
                                    
                                    // Create a span to preserve styling
                                    var span = document.createElement('span');
                                    span.className = 'voxel-custom-message';
                                    span.textContent = customMessage;
                                    
                                    // Replace the text node with our span
                                    longestNode.parentNode.replaceChild(span, longestNode);
                                    
                                    // Clear other text nodes that might contain parts of the old message
                                    textNodes.forEach(function(node) {
                                        if (node !== longestNode && 
                                            (node.textContent.toLowerCase().includes('submitted') || 
                                             node.textContent.toLowerCase().includes('review'))) {
                                            node.textContent = '';
                                        }
                                    });
                                }
                            } else {
                                // Simple element - preserve styling by keeping the element structure
                                // Get computed styles to maintain appearance
                                var computedStyle = window.getComputedStyle(element);
                                element.innerHTML = '<span class="voxel-custom-message" style="' +
                                    'font-size: ' + computedStyle.fontSize + ';' +
                                    'color: ' + computedStyle.color + ';' +
                                    'font-family: ' + computedStyle.fontFamily + ';' +
                                    'font-weight: ' + computedStyle.fontWeight + ';' +
                                    'line-height: ' + computedStyle.lineHeight + ';' +
                                    '">' + customMessage + '</span>';
                            }
                        }
                    });
                });
                
                // Also check standalone text nodes, but be more careful
                var walker = document.createTreeWalker(
                    document.body,
                    NodeFilter.SHOW_TEXT,
                    {
                        acceptNode: function(node) {
                            // Skip text nodes inside script, style, or noscript tags
                            var parent = node.parentNode;
                            while (parent) {
                                var tagName = parent.tagName ? parent.tagName.toLowerCase() : '';
                                if (tagName === 'script' || tagName === 'style' || tagName === 'noscript') {
                                    return NodeFilter.FILTER_REJECT;
                                }
                                parent = parent.parentNode;
                            }
                            
                            // Skip if text is too short or too long (likely code)
                            var text = node.textContent.trim();
                            if (text.length < 15 || text.length > 200) {
                                return NodeFilter.FILTER_REJECT;
                            }
                            
                            // Skip if it looks like code (contains common code patterns)
                            if (text.includes('function') || text.includes('var ') || text.includes('const ') || 
                                text.includes('let ') || text.includes('return ') || text.includes('console.')) {
                                return NodeFilter.FILTER_REJECT;
                            }
                            
                            return NodeFilter.FILTER_ACCEPT;
                        }
                    },
                    false
                );
                
                var textNodes = [];
                var node;
                
                while (node = walker.nextNode()) {
                    textNodes.push(node);
                }
                
                textNodes.forEach(function(textNode) {
                    var nodeText = textNode.textContent.toLowerCase();
                    
                    // Be more specific - look for complete submission messages
                    var submissionPhrases = [
                        'submitted for review',
                        'has been submitted',
                        'post has been submitted',
                        'listing has been submitted',
                        'event has been submitted',
                        'thank you for your submission',
                        'your submission has been received'
                    ];
                    
                    var isSubmissionMessage = submissionPhrases.some(function(phrase) {
                        return nodeText.includes(phrase.toLowerCase());
                    });
                    
                    if (isSubmissionMessage) {
                        console.log('Voxel Toolkit: Replacing entire text node content');
                        // Replace the entire text content
                        textNode.textContent = customMessage;
                    }
                });
            }
            
            // Run replacement multiple times to catch dynamic content
            replaceSubmissionMessages();
            setTimeout(replaceSubmissionMessages, 100);
            setTimeout(replaceSubmissionMessages, 500);
            setTimeout(replaceSubmissionMessages, 1000);
            
            // Watch for DOM changes - wait for body to be ready
            if (typeof MutationObserver !== 'undefined' && document.body) {
                var isReplacing = false; // Flag to prevent infinite loops
                
                var observer = new MutationObserver(function(mutations) {
                    if (isReplacing) return; // Don't trigger during our own replacements
                    
                    var shouldReplace = false;
                    mutations.forEach(function(mutation) {
                        // Only trigger on significant changes, not our own replacements
                        if (mutation.type === 'childList' && mutation.addedNodes.length > 0) {
                            // Check if added nodes contain potential submission messages
                            for (var i = 0; i < mutation.addedNodes.length; i++) {
                                var node = mutation.addedNodes[i];
                                if (node.nodeType === Node.ELEMENT_NODE) {
                                    var text = node.textContent ? node.textContent.toLowerCase() : '';
                                    if (text.includes('submitted') || text.includes('review') || text.includes('success')) {
                                        shouldReplace = true;
                                        break;
                                    }
                                }
                            }
                        }
                    });
                    
                    if (shouldReplace) {
                        setTimeout(function() {
                            isReplacing = true;
                            replaceSubmissionMessages();
                            setTimeout(function() {
                                isReplacing = false;
                            }, 100);
                        }, 10);
                    }
                });
                
                // Update replaceSubmissionMessages to set the flag
                var originalReplace = replaceSubmissionMessages;
                replaceSubmissionMessages = function() {
                    isReplacing = true;
                    originalReplace();
                    setTimeout(function() {
                        isReplacing = false;
                    }, 100);
                };
                
                try {
                    observer.observe(document.body, {
                        childList: true,
                        subtree: true
                    });
                } catch (e) {
                    console.log('Voxel Toolkit: Could not set up MutationObserver:', e);
                }
            }
            
            // Hook into jQuery AJAX if available
            if (typeof jQuery !== 'undefined') {
                jQuery(document).ajaxSuccess(function(event, xhr, settings) {
                    console.log('Voxel Toolkit: AJAX success - checking for messages to replace');
                    setTimeout(replaceSubmissionMessages, 50);
                    setTimeout(replaceSubmissionMessages, 200);
                });
            }
        })();
        </script>
        <?php
    }
    
    /**
     * Capture AJAX responses for message replacement
     */
    public function capture_ajax_response($handler) {
        return $handler; // For now, just return the original handler
    }
    
    /**
     * Get available post types for settings
     */
    public static function get_available_post_types() {
        $post_types = get_post_types(array('public' => true), 'objects');
        $available = array();
        
        foreach ($post_types as $post_type) {
            if (in_array($post_type->name, array('attachment', 'page'))) {
                continue;
            }
            $available[$post_type->name] = $post_type->labels->name;
        }
        
        return $available;
    }
}