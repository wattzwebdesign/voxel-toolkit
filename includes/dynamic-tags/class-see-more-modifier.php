<?php
/**
 * See More Modifier
 *
 * Truncates text and adds an expandable "See More" / "See Less" toggle link.
 *
 * Usage: @post(description)|see_more(100, words, See More, See Less)
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

// Only define if the parent class exists (Voxel theme is loaded)
if (!class_exists('\Voxel\Dynamic_Data\Modifiers\Base_Modifier')) {
    return;
}

class Voxel_Toolkit_See_More_Modifier extends \Voxel\Dynamic_Data\Modifiers\Base_Modifier {

    public function get_label(): string {
        return 'See More (Expandable)';
    }

    public function get_key(): string {
        return 'see_more';
    }

    public function get_description(): string {
        return 'Truncates text and adds an expandable "See More" link';
    }

    protected function define_args(): void {
        // Argument 0: Limit
        $this->define_arg([
            'type' => 'text',
            'label' => 'Limit',
            'placeholder' => '100',
            'description' => 'Number of words or characters to show',
        ]);

        // Argument 1: Type (words or characters)
        $this->define_arg([
            'type' => 'select',
            'label' => 'Truncate by',
            'choices' => [
                'words' => 'Words',
                'characters' => 'Characters',
            ],
        ]);

        // Argument 2: Link text
        $this->define_arg([
            'type' => 'text',
            'label' => 'Link text',
            'placeholder' => 'See More',
            'description' => 'Text shown for the expand link',
        ]);

        // Argument 3: Collapse text
        $this->define_arg([
            'type' => 'text',
            'label' => 'Collapse text',
            'placeholder' => 'See Less',
            'description' => 'Text shown for the collapse link',
        ]);
    }

    public function apply(string $value) {
        // Get arguments with defaults
        $limit = absint($this->get_arg(0)) ?: 100;
        $type = $this->get_arg(1) ?: 'words';
        $link_text = $this->get_arg(2) ?: 'See More';
        $collapse_text = $this->get_arg(3) ?: 'See Less';

        // Check if truncation is needed
        $needs_truncation = $this->needs_truncation($value, $limit, $type);

        if (!$needs_truncation) {
            return $value; // Return full content if under limit
        }

        // Get truncated content
        $truncated = $this->truncate_content($value, $limit, $type);

        // Generate unique ID for this instance
        $unique_id = 'vt-see-more-' . wp_unique_id();

        // Enqueue assets
        $this->enqueue_assets();

        // Build HTML output
        return $this->build_html($unique_id, $truncated, $value, $link_text, $collapse_text);
    }

    private function needs_truncation($value, $limit, $type) {
        $plain_text = strip_tags($value);

        if ($type === 'characters') {
            return mb_strlen($plain_text) > $limit;
        }

        return str_word_count($plain_text) > $limit;
    }

    private function truncate_content($value, $limit, $type) {
        $plain_text = strip_tags($value);

        if ($type === 'characters') {
            return mb_substr($plain_text, 0, $limit);
        }

        // Truncate by words
        $words = explode(' ', $plain_text);
        return implode(' ', array_slice($words, 0, $limit));
    }

    private function build_html($id, $truncated, $full, $link_text, $collapse_text) {
        $truncated_escaped = esc_html($truncated);
        $full_escaped = wp_kses_post($full);
        $link_escaped = esc_html($link_text);
        $collapse_escaped = esc_html($collapse_text);

        return sprintf(
            '<div class="vt-see-more-wrapper" id="%s" data-expand-text="%s" data-collapse-text="%s">
                <div class="vt-see-more-truncated">%s<span class="vt-see-more-ellipsis">... </span><span class="vt-see-more-link" role="button" tabindex="0"><strong>%s</strong></span></div>
                <div class="vt-see-more-full" style="display: none;">%s<span class="vt-see-more-ellipsis"> </span><span class="vt-see-more-link vt-see-more-collapse" role="button" tabindex="0"><strong>%s</strong></span></div>
            </div>',
            esc_attr($id),
            esc_attr($link_escaped),
            esc_attr($collapse_escaped),
            $truncated_escaped,
            $link_escaped,
            $full_escaped,
            $collapse_escaped
        );
    }

    private function enqueue_assets() {
        static $enqueued = false;
        if ($enqueued) return;
        $enqueued = true;

        add_action('wp_footer', array($this, 'output_inline_styles_scripts'), 99);
    }

    public function output_inline_styles_scripts() {
        ?>
        <style>
        .vt-see-more-wrapper { }
        .vt-see-more-link {
            color: inherit;
            cursor: pointer;
        }
        .vt-see-more-link:hover {
            text-decoration: underline;
        }
        .vt-see-more-full {
            overflow: hidden;
        }
        .vt-see-more-ellipsis {
            /* Keeps ... inline with text */
        }
        </style>
        <script>
        (function() {
            document.addEventListener('click', function(e) {
                var link = e.target.closest('.vt-see-more-link');
                if (!link) return;

                var wrapper = link.closest('.vt-see-more-wrapper');
                if (!wrapper) return;

                var truncated = wrapper.querySelector('.vt-see-more-truncated');
                var full = wrapper.querySelector('.vt-see-more-full');

                if (!truncated || !full) return;

                var isExpanded = wrapper.classList.contains('vt-see-more-expanded');

                if (isExpanded) {
                    // Collapse: show truncated, hide full
                    wrapper.classList.remove('vt-see-more-expanded');
                    full.style.display = 'none';
                    truncated.style.display = 'block';
                } else {
                    // Expand: hide truncated, show full with slide animation
                    wrapper.classList.add('vt-see-more-expanded');
                    truncated.style.display = 'none';
                    full.style.display = 'block';
                    full.style.maxHeight = '0';
                    full.style.transition = 'max-height 0.3s ease-out';

                    // Trigger reflow then animate
                    full.offsetHeight;
                    full.style.maxHeight = full.scrollHeight + 'px';

                    // Clean up after animation
                    setTimeout(function() {
                        full.style.maxHeight = '';
                        full.style.transition = '';
                    }, 300);
                }
            });

            // Keyboard accessibility
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    var link = e.target.closest('.vt-see-more-link');
                    if (link) {
                        e.preventDefault();
                        link.click();
                    }
                }
            });
        })();
        </script>
        <?php
    }
}
