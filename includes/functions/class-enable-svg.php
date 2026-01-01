<?php
/**
 * Enable SVG Uploads Function
 *
 * Allows SVG file uploads in WordPress media library with security sanitization.
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Enable_SVG {

    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    public function __construct() {
        $this->init();
    }

    /**
     * Initialize the feature
     */
    private function init() {
        // Allow SVG uploads
        add_filter('upload_mimes', array($this, 'allow_svg_upload'));

        // Fix SVG display in media library
        add_filter('wp_check_filetype_and_ext', array($this, 'fix_svg_mime_type'), 10, 5);

        // Add SVG support for media library thumbnails
        add_filter('wp_prepare_attachment_for_js', array($this, 'fix_svg_media_library_display'), 10, 3);

        // Sanitize SVG on upload
        add_filter('wp_handle_upload_prefilter', array($this, 'sanitize_svg_upload'));

        // Add admin styles for SVG display
        add_action('admin_head', array($this, 'svg_admin_styles'));
    }

    /**
     * Allow SVG mime type for uploads
     */
    public function allow_svg_upload($mimes) {
        $mimes['svg'] = 'image/svg+xml';
        $mimes['svgz'] = 'image/svg+xml';
        return $mimes;
    }

    /**
     * Fix SVG mime type detection
     */
    public function fix_svg_mime_type($data, $file, $filename, $mimes, $real_mime = '') {
        if (empty($data['ext']) || empty($data['type'])) {
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if ($ext === 'svg') {
                $data['ext'] = 'svg';
                $data['type'] = 'image/svg+xml';
            } elseif ($ext === 'svgz') {
                $data['ext'] = 'svgz';
                $data['type'] = 'image/svg+xml';
            }
        }
        return $data;
    }

    /**
     * Fix SVG display in media library
     */
    public function fix_svg_media_library_display($response, $attachment, $meta) {
        if ($response['mime'] === 'image/svg+xml') {
            $svg_path = get_attached_file($attachment->ID);

            if (file_exists($svg_path)) {
                // Get SVG dimensions
                $svg_content = file_get_contents($svg_path);
                $dimensions = $this->get_svg_dimensions($svg_content);

                if ($dimensions) {
                    $response['width'] = $dimensions['width'];
                    $response['height'] = $dimensions['height'];
                }

                // Set sizes for display
                $response['sizes'] = array(
                    'full' => array(
                        'url' => $response['url'],
                        'width' => isset($dimensions['width']) ? $dimensions['width'] : 100,
                        'height' => isset($dimensions['height']) ? $dimensions['height'] : 100,
                        'orientation' => 'landscape',
                    ),
                    'thumbnail' => array(
                        'url' => $response['url'],
                        'width' => 150,
                        'height' => 150,
                        'orientation' => 'landscape',
                    ),
                    'medium' => array(
                        'url' => $response['url'],
                        'width' => 300,
                        'height' => 300,
                        'orientation' => 'landscape',
                    ),
                );
            }
        }
        return $response;
    }

    /**
     * Get SVG dimensions from content
     */
    private function get_svg_dimensions($svg_content) {
        // Try to get dimensions from viewBox or width/height attributes
        $width = null;
        $height = null;

        // Check for width attribute
        if (preg_match('/\bwidth\s*=\s*["\']?(\d+(?:\.\d+)?)/i', $svg_content, $matches)) {
            $width = floatval($matches[1]);
        }

        // Check for height attribute
        if (preg_match('/\bheight\s*=\s*["\']?(\d+(?:\.\d+)?)/i', $svg_content, $matches)) {
            $height = floatval($matches[1]);
        }

        // Try viewBox if width/height not found
        if ((!$width || !$height) && preg_match('/viewBox\s*=\s*["\']?\s*[\d.]+\s+[\d.]+\s+([\d.]+)\s+([\d.]+)/i', $svg_content, $matches)) {
            if (!$width) {
                $width = floatval($matches[1]);
            }
            if (!$height) {
                $height = floatval($matches[2]);
            }
        }

        if ($width && $height) {
            return array(
                'width' => round($width),
                'height' => round($height),
            );
        }

        return null;
    }

    /**
     * Sanitize SVG files on upload
     */
    public function sanitize_svg_upload($file) {
        if ($file['type'] === 'image/svg+xml' || (isset($file['name']) && preg_match('/\.svgz?$/i', $file['name']))) {
            // Read the file
            $svg_content = file_get_contents($file['tmp_name']);

            if ($svg_content === false) {
                $file['error'] = __('Could not read SVG file.', 'voxel-toolkit');
                return $file;
            }

            // Handle gzipped SVGs
            if (preg_match('/\.svgz$/i', $file['name'])) {
                $svg_content = gzdecode($svg_content);
                if ($svg_content === false) {
                    $file['error'] = __('Could not decompress SVGZ file.', 'voxel-toolkit');
                    return $file;
                }
            }

            // Sanitize the SVG
            $sanitized = $this->sanitize_svg($svg_content);

            if ($sanitized === false) {
                $file['error'] = __('SVG file contains potentially unsafe content.', 'voxel-toolkit');
                return $file;
            }

            // Write sanitized content back
            file_put_contents($file['tmp_name'], $sanitized);
        }

        return $file;
    }

    /**
     * Sanitize SVG content - remove potentially dangerous elements
     */
    private function sanitize_svg($svg_content) {
        // Load the SVG
        $dom = new DOMDocument();
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;

        // Suppress errors for malformed SVGs
        libxml_use_internal_errors(true);
        $loaded = $dom->loadXML($svg_content);
        libxml_clear_errors();

        if (!$loaded) {
            return false;
        }

        // Get the SVG element
        $svg_elements = $dom->getElementsByTagName('svg');
        if ($svg_elements->length === 0) {
            return false;
        }

        // Remove dangerous elements
        $dangerous_elements = array(
            'script',
            'use',
            'foreignObject',
            'set',
            'animate',
            'animateMotion',
            'animateTransform',
        );

        foreach ($dangerous_elements as $tag) {
            $elements = $dom->getElementsByTagName($tag);
            while ($elements->length > 0) {
                $element = $elements->item(0);
                $element->parentNode->removeChild($element);
            }
        }

        // Remove event handler attributes from all elements
        $xpath = new DOMXPath($dom);
        $all_elements = $xpath->query('//*');

        $dangerous_attributes = array(
            'onload', 'onerror', 'onclick', 'onmouseover', 'onmouseout',
            'onmousedown', 'onmouseup', 'onfocus', 'onblur', 'onchange',
            'onsubmit', 'onreset', 'onselect', 'onabort', 'ondblclick',
            'onkeydown', 'onkeypress', 'onkeyup', 'onunload', 'onresize',
        );

        foreach ($all_elements as $element) {
            // Remove dangerous attributes
            foreach ($dangerous_attributes as $attr) {
                if ($element->hasAttribute($attr)) {
                    $element->removeAttribute($attr);
                }
            }

            // Remove href with javascript:
            if ($element->hasAttribute('href')) {
                $href = $element->getAttribute('href');
                if (preg_match('/^\s*javascript:/i', $href)) {
                    $element->removeAttribute('href');
                }
            }

            // Remove xlink:href with javascript:
            if ($element->hasAttributeNS('http://www.w3.org/1999/xlink', 'href')) {
                $href = $element->getAttributeNS('http://www.w3.org/1999/xlink', 'href');
                if (preg_match('/^\s*javascript:/i', $href)) {
                    $element->removeAttributeNS('http://www.w3.org/1999/xlink', 'href');
                }
            }
        }

        return $dom->saveXML();
    }

    /**
     * Add admin styles for SVG display in media library
     */
    public function svg_admin_styles() {
        echo '<style>
            .attachment-266x266.size-266x266[src$=".svg"],
            .attachment-150x150.size-150x150[src$=".svg"],
            img[src$=".svg"].attachment-post-thumbnail {
                width: 100%;
                height: auto;
            }
            .media-icon img[src$=".svg"] {
                width: 100%;
                height: auto;
                max-width: 48px;
            }
            .attachment-info .thumbnail img[src$=".svg"] {
                width: 100%;
                height: auto;
            }
            td.media-icon img[src$=".svg"] {
                width: 60px;
                height: 60px;
            }
        </style>';
    }

    /**
     * Render settings for this function
     */
    public function render_settings($function_settings) {
        ?>
        <div class="voxel-toolkit-setting">
            <h3><?php _e('Enable SVG Uploads', 'voxel-toolkit'); ?></h3>
            <p class="description">
                <?php _e('Allows SVG and SVGZ file uploads to the WordPress media library with automatic security sanitization.', 'voxel-toolkit'); ?>
            </p>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Status', 'voxel-toolkit'); ?></th>
                    <td>
                        <span style="color: #46b450; font-weight: 600;">
                            <span class="dashicons dashicons-yes-alt" style="vertical-align: middle;"></span>
                            <?php _e('SVG uploads enabled', 'voxel-toolkit'); ?>
                        </span>
                        <p class="description" style="margin-top: 8px;">
                            <?php _e('You can now upload SVG files to the media library.', 'voxel-toolkit'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Security', 'voxel-toolkit'); ?></th>
                    <td>
                        <ul style="margin: 0; list-style: disc; padding-left: 20px;">
                            <li><?php _e('Removes JavaScript and event handlers from SVGs', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Strips potentially dangerous elements (script, foreignObject, etc.)', 'voxel-toolkit'); ?></li>
                            <li><?php _e('Sanitizes SVG content on upload', 'voxel-toolkit'); ?></li>
                        </ul>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Supported formats', 'voxel-toolkit'); ?></th>
                    <td>
                        <ul style="margin: 0; list-style: disc; padding-left: 20px;">
                            <li><code>.svg</code> - <?php _e('Standard SVG files', 'voxel-toolkit'); ?></li>
                            <li><code>.svgz</code> - <?php _e('Compressed SVG files', 'voxel-toolkit'); ?></li>
                        </ul>
                    </td>
                </tr>
            </table>
        </div>
        <?php
    }
}
