<?php
/**
 * Google Analytics & Custom Tags Function
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Google_Analytics {
    
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
        // Add Google Analytics and custom tags to head
        add_action('wp_head', array($this, 'add_head_tags'), 1);
        
        // Add custom tags after opening body tag
        add_action('wp_body_open', array($this, 'add_body_tags'), 1);
        
        // Add custom tags before closing body tag
        add_action('wp_footer', array($this, 'add_footer_tags'), 999);
        
        // Admin hooks for preview
        if (is_admin()) {
            add_action('admin_head', array($this, 'add_admin_styles'));
        }
    }
    
    /**
     * Add Google Analytics and custom tags to head
     */
    public function add_head_tags() {
        $settings = $this->get_settings();
        
        // Google Analytics 4 (gtag)
        if (!empty($settings['ga4_measurement_id'])) {
            $this->output_ga4_gtag($settings['ga4_measurement_id']);
        }
        
        // Universal Analytics (deprecated but still supported)
        if (!empty($settings['ua_tracking_id'])) {
            $this->output_universal_analytics($settings['ua_tracking_id']);
        }
        
        // Google Tag Manager
        if (!empty($settings['gtm_container_id'])) {
            $this->output_gtm_head($settings['gtm_container_id']);
        }
        
        // Custom head tags
        if (!empty($settings['custom_head_tags'])) {
            echo "\n<!-- Voxel Toolkit: Custom Head Tags -->\n";
            echo $settings['custom_head_tags'];
            echo "\n<!-- End Custom Head Tags -->\n";
        }
    }
    
    /**
     * Add custom tags after opening body tag
     */
    public function add_body_tags() {
        $settings = $this->get_settings();
        
        // Google Tag Manager (noscript)
        if (!empty($settings['gtm_container_id'])) {
            $this->output_gtm_body($settings['gtm_container_id']);
        }
        
        // Custom body tags
        if (!empty($settings['custom_body_tags'])) {
            echo "\n<!-- Voxel Toolkit: Custom Body Tags -->\n";
            echo $settings['custom_body_tags'];
            echo "\n<!-- End Custom Body Tags -->\n";
        }
    }
    
    /**
     * Add custom tags before closing body tag
     */
    public function add_footer_tags() {
        $settings = $this->get_settings();
        
        // Custom footer tags
        if (!empty($settings['custom_footer_tags'])) {
            echo "\n<!-- Voxel Toolkit: Custom Footer Tags -->\n";
            echo $settings['custom_footer_tags'];
            echo "\n<!-- End Custom Footer Tags -->\n";
        }
    }
    
    /**
     * Output Google Analytics 4 (gtag) code
     */
    private function output_ga4_gtag($measurement_id) {
        ?>
        <!-- Google Analytics 4 (GA4) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($measurement_id); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', '<?php echo esc_js($measurement_id); ?>');
        </script>
        <?php
    }
    
    /**
     * Output Universal Analytics code (legacy)
     */
    private function output_universal_analytics($tracking_id) {
        ?>
        <!-- Universal Analytics (Legacy) -->
        <script>
            (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
            (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
            m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
            })(window,document,'script','https://www.google-analytics.com/analytics.js','ga');
            
            ga('create', '<?php echo esc_js($tracking_id); ?>', 'auto');
            ga('send', 'pageview');
        </script>
        <?php
    }
    
    /**
     * Output Google Tag Manager head code
     */
    private function output_gtm_head($container_id) {
        ?>
        <!-- Google Tag Manager -->
        <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
        new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
        j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
        'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
        })(window,document,'script','dataLayer','<?php echo esc_js($container_id); ?>');</script>
        <!-- End Google Tag Manager -->
        <?php
    }
    
    /**
     * Output Google Tag Manager body code
     */
    private function output_gtm_body($container_id) {
        ?>
        <!-- Google Tag Manager (noscript) -->
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr($container_id); ?>"
        height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
        <!-- End Google Tag Manager (noscript) -->
        <?php
    }
    
    /**
     * Get Google Analytics settings
     */
    private function get_settings() {
        $voxel_toolkit_options = get_option('voxel_toolkit_options', array());
        return isset($voxel_toolkit_options['google_analytics']) ? $voxel_toolkit_options['google_analytics'] : array();
    }
    
    /**
     * Add admin styles for settings preview
     */
    public function add_admin_styles() {
        ?>
        <style>
        .vt-ga-preview {
            background: #f8f9fa;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 15px;
            margin: 10px 0;
            font-family: monospace;
            font-size: 12px;
            white-space: pre-wrap;
            max-height: 200px;
            overflow-y: auto;
        }
        .vt-ga-preview-title {
            font-weight: bold;
            margin-bottom: 10px;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .vt-ga-settings-section {
            margin-bottom: 30px;
            padding: 20px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        .vt-ga-settings-section h3 {
            margin-top: 0;
            color: #2271b1;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .vt-ga-input-group {
            margin-bottom: 15px;
        }
        .vt-ga-input-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
        }
        .vt-ga-input-group input[type="text"] {
            width: 100%;
            max-width: 400px;
        }
        .vt-ga-input-group textarea {
            width: 100%;
            height: 120px;
            font-family: monospace;
            font-size: 12px;
        }
        .vt-ga-help-text {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
            font-style: italic;
        }
        .vt-ga-warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
            padding: 10px;
            margin: 10px 0;
            color: #856404;
        }
        </style>
        <?php
    }
}