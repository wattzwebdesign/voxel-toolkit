#!/usr/bin/env php
<?php
/**
 * POT File Generator for Voxel Toolkit
 *
 * Scans all PHP files and extracts translatable strings to generate a POT file.
 *
 * Usage: php generate-pot.php
 */

class POTGenerator {
    private $text_domain = 'voxel-toolkit';
    private $plugin_dir;
    private $strings = [];

    // Directories to exclude
    private $exclude_dirs = [
        'dist',
        'vendor',
        'node_modules',
        '.git',
    ];

    // Translation functions to scan for
    private $functions = [
        '__' => 1,           // __($text, $domain)
        '_e' => 1,           // _e($text, $domain)
        'esc_html__' => 1,   // esc_html__($text, $domain)
        'esc_attr__' => 1,   // esc_attr__($text, $domain)
        'esc_html_e' => 1,   // esc_html_e($text, $domain)
        'esc_attr_e' => 1,   // esc_attr_e($text, $domain)
        '_x' => 1,           // _x($text, $context, $domain)
        '_ex' => 1,          // _ex($text, $context, $domain)
        'esc_html_x' => 1,   // esc_html_x($text, $context, $domain)
        'esc_attr_x' => 1,   // esc_attr_x($text, $context, $domain)
        '_n' => 1,           // _n($single, $plural, $number, $domain)
        '_nx' => 1,          // _nx($single, $plural, $number, $context, $domain)
    ];

    public function __construct($plugin_dir) {
        $this->plugin_dir = rtrim($plugin_dir, '/');
    }

    /**
     * Generate POT file
     */
    public function generate() {
        echo "Scanning PHP files in {$this->plugin_dir}...\n";

        // Find all PHP files
        $files = $this->findPHPFiles($this->plugin_dir);
        echo "Found " . count($files) . " PHP files\n";

        // Extract strings from each file
        foreach ($files as $file) {
            $this->extractStrings($file);
        }

        echo "Found " . count($this->strings) . " unique translatable strings\n";

        // Generate POT content
        $pot_content = $this->generatePOTContent();

        // Write POT file
        $pot_file = $this->plugin_dir . '/languages/voxel-toolkit.pot';
        file_put_contents($pot_file, $pot_content);

        echo "POT file generated: $pot_file\n";

        return $pot_file;
    }

    /**
     * Find all PHP files recursively
     */
    private function findPHPFiles($dir) {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            // Skip excluded directories
            $path = $file->getPathname();
            $skip = false;
            foreach ($this->exclude_dirs as $exclude) {
                if (strpos($path, '/' . $exclude . '/') !== false) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // Only PHP files
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $path;
            }
        }

        sort($files);
        return $files;
    }

    /**
     * Extract translatable strings from a PHP file
     */
    private function extractStrings($file) {
        $content = file_get_contents($file);
        $relative_path = str_replace($this->plugin_dir . '/', '', $file);

        // Build regex pattern for all translation functions
        $func_pattern = implode('|', array_map('preg_quote', array_keys($this->functions)));

        // Pattern to match translation function calls
        // Matches: function_name( 'string' or "string", ... 'voxel-toolkit' )
        $pattern = '/\b(' . $func_pattern . ')\s*\(\s*([\'"])((?:(?!\2)[^\\\\]|\\\\.)*)\2/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $func_name = $match[1][0];
                $quote = $match[2][0];
                $string = $match[3][0];
                $offset = $match[0][1];

                // Unescape the string
                $string = $this->unescapeString($string, $quote);

                // Skip empty strings
                if (empty(trim($string))) continue;

                // Check if this call uses our text domain
                // Look ahead in the content to find the text domain
                $call_end_pos = $offset + strlen($match[0][0]);
                $remaining = substr($content, $call_end_pos, 500);

                // Check for text domain in the function call
                if (strpos($remaining, "'voxel-toolkit'") !== false ||
                    strpos($remaining, '"voxel-toolkit"') !== false) {

                    // Calculate line number
                    $line_number = substr_count(substr($content, 0, $offset), "\n") + 1;

                    // Store the string with its location
                    $key = $string;
                    if (!isset($this->strings[$key])) {
                        $this->strings[$key] = [
                            'string' => $string,
                            'locations' => [],
                        ];
                    }
                    $this->strings[$key]['locations'][] = "$relative_path:$line_number";
                }
            }
        }

        // Also scan for _n() and _nx() plural forms
        $this->extractPluralStrings($content, $relative_path);
    }

    /**
     * Extract plural strings (_n, _nx)
     */
    private function extractPluralStrings($content, $relative_path) {
        // Pattern for _n($single, $plural, $number, $domain)
        $pattern = '/\b_n[x]?\s*\(\s*([\'"])((?:(?!\1)[^\\\\]|\\\\.)*)\1\s*,\s*([\'"])((?:(?!\3)[^\\\\]|\\\\.)*)\3/s';

        if (preg_match_all($pattern, $content, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($matches as $match) {
                $single = $this->unescapeString($match[2][0], $match[1][0]);
                $plural = $this->unescapeString($match[4][0], $match[3][0]);
                $offset = $match[0][1];

                // Check for text domain
                $call_end_pos = $offset + strlen($match[0][0]);
                $remaining = substr($content, $call_end_pos, 500);

                if (strpos($remaining, "'voxel-toolkit'") !== false ||
                    strpos($remaining, '"voxel-toolkit"') !== false) {

                    $line_number = substr_count(substr($content, 0, $offset), "\n") + 1;

                    // Store singular
                    if (!empty(trim($single))) {
                        $key = $single;
                        if (!isset($this->strings[$key])) {
                            $this->strings[$key] = [
                                'string' => $single,
                                'plural' => $plural,
                                'locations' => [],
                            ];
                        }
                        $this->strings[$key]['locations'][] = "$relative_path:$line_number";
                    }
                }
            }
        }
    }

    /**
     * Unescape a PHP string
     */
    private function unescapeString($string, $quote) {
        if ($quote === '"') {
            // Double-quoted string escapes
            $string = str_replace(['\\n', '\\r', '\\t', '\\"', '\\\\'], ["\n", "\r", "\t", '"', '\\'], $string);
        } else {
            // Single-quoted string escapes
            $string = str_replace(["\\'", '\\\\'], ["'", '\\'], $string);
        }
        return $string;
    }

    /**
     * Escape a string for POT file
     */
    private function escapeForPOT($string) {
        $string = str_replace('\\', '\\\\', $string);
        $string = str_replace('"', '\\"', $string);
        $string = str_replace("\n", '\\n', $string);
        $string = str_replace("\r", '\\r', $string);
        $string = str_replace("\t", '\\t', $string);
        return $string;
    }

    /**
     * Generate POT file content
     */
    private function generatePOTContent() {
        $date = date('Y-m-d H:i+0000');
        $year = date('Y');

        $content = "# Copyright (C) $year Voxel Toolkit
# This file is distributed under the same license as the Voxel Toolkit plugin.
msgid \"\"
msgstr \"\"
\"Project-Id-Version: Voxel Toolkit 1.6.1\\n\"
\"Report-Msgid-Bugs-To: https://voxel-toolkit.com/support\\n\"
\"POT-Creation-Date: $date\\n\"
\"MIME-Version: 1.0\\n\"
\"Content-Type: text/plain; charset=UTF-8\\n\"
\"Content-Transfer-Encoding: 8bit\\n\"
\"PO-Revision-Date: $year-MO-DA HO:MI+ZONE\\n\"
\"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n\"
\"Language-Team: LANGUAGE <LL@li.org>\\n\"
\"X-Generator: Voxel Toolkit POT Generator\\n\"
\"X-Domain: voxel-toolkit\\n\"

";

        // Sort strings alphabetically
        ksort($this->strings);

        foreach ($this->strings as $data) {
            // Add location comments
            foreach ($data['locations'] as $location) {
                $content .= "#: $location\n";
            }

            // Add the string
            $escaped = $this->escapeForPOT($data['string']);

            // Handle multiline strings
            if (strpos($escaped, '\\n') !== false) {
                $content .= "msgid \"\"\n";
                $lines = explode('\\n', $escaped);
                foreach ($lines as $i => $line) {
                    $suffix = ($i < count($lines) - 1) ? '\\n' : '';
                    $content .= "\"$line$suffix\"\n";
                }
            } else {
                $content .= "msgid \"$escaped\"\n";
            }

            // Add plural form if exists
            if (isset($data['plural'])) {
                $plural_escaped = $this->escapeForPOT($data['plural']);
                $content .= "msgid_plural \"$plural_escaped\"\n";
                $content .= "msgstr[0] \"\"\n";
                $content .= "msgstr[1] \"\"\n";
            } else {
                $content .= "msgstr \"\"\n";
            }

            $content .= "\n";
        }

        return $content;
    }
}

// Run if called from command line
if (php_sapi_name() === 'cli') {
    $plugin_dir = __DIR__;

    $generator = new POTGenerator($plugin_dir);
    $generator->generate();
}
