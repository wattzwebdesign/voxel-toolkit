<?php
/**
 * Extended Keywords Filter
 *
 * Extends Voxel's Keywords Filter to include taxonomy synonyms in search indexing.
 *
 * @package Voxel_Toolkit
 */

namespace Voxel_Toolkit\Filters;

if (!defined('ABSPATH')) {
    exit;
}

class Keywords_Extended_Filter extends \Voxel\Post_Types\Filters\Keywords_Filter {

    /**
     * Extended props with synonyms toggle
     */
    protected $props = [
        'type' => 'keywords',
        'label' => 'Keywords',
        'placeholder' => '',
        'sources' => [
            'title',
            'description',
        ],
        'include_synonyms' => true,
    ];

    /**
     * Supported field types (inherited from parent)
     */
    protected $supported_field_types = [
        'title',
        'description',
        'text',
        'texteditor',
        'location',
        'taxonomy',
        'profile-name',
    ];

    /**
     * Get filter configuration models
     *
     * Extends parent to add synonyms toggle inside the sources section.
     *
     * @return array
     */
    public function get_models(): array {
        return [
            'label' => $this->get_model('label', ['classes' => 'x-col-12']),
            'placeholder' => $this->get_model('placeholder', ['classes' => 'x-col-6']),
            'key' => $this->get_model('key', ['classes' => 'x-col-6']),

            'sources' => function() { ?>
                <div class="ts-form-group x-col-12 ts-checkbox">
                    <label>Look for matches in:</label>
                    <div class="ts-checkbox-container">
                        <label v-for="field in $root.getFieldsByType( <?= esc_attr(wp_json_encode($this->supported_field_types)) ?> )"
                            class="container-checkbox">
                            {{ field.label }}
                            <input type="checkbox" :value="field.key" v-model="filter.sources">
                            <span class="checkmark"></span>
                        </label>

                        <template v-for="repeater in $root.getFieldsByType('repeater')">
                            <keywords-source-repeater
                                :keywords-filter="filter"
                                :repeater="repeater"
                                :key-base="repeater.key"
                                :label-base="repeater.label"
                                :field-types="<?= esc_attr(wp_json_encode($this->supported_field_types)) ?>"
                            ></keywords-source-repeater>
                        </template>

                        <!-- Synonyms toggle -->
                        <label class="container-checkbox">
                            <?php echo esc_html__('Taxonomy Synonyms', 'voxel-toolkit'); ?>
                            <input type="checkbox" v-model="filter.include_synonyms">
                            <span class="checkmark"></span>
                        </label>
                    </div>
                </div>
            <?php },
            'icon' => $this->get_model('icon', ['classes' => 'x-col-12']),
        ];
    }

    /**
     * Index post data for keyword search
     *
     * Overrides parent to include synonyms from taxonomy terms.
     *
     * @param \Voxel\Post $post The post to index
     * @return array Indexed data
     */
    public function index(\Voxel\Post $post): array {
        $values = [];
        $include_synonyms = $this->props['include_synonyms'] ?? true;

        foreach ($this->props['sources'] as $field_key) {
            $parts = explode('->', $field_key);
            $original_parts = $parts;
            $field = $post->get_field($parts[0]);

            if (!$field) {
                continue;
            }

            if ($field->get_type() === 'repeater') {
                $repeater_value = $field->get_value();
                if (!is_array($repeater_value)) {
                    continue;
                }

                array_shift($parts);
                do {
                    $field = $field->get_fields()[$parts[0]] ?? null;
                    array_shift($parts);
                } while ($field && $field->get_type() === 'repeater' && count($parts));

                if ($field) {
                    $extracted_values = [];
                    $this->_extract_values_from_repeater($repeater_value, array_slice($original_parts, 1), $extracted_values);

                    if ($extracted_values !== null) {
                        if ($field->get_type() === 'taxonomy') {
                            foreach ($extracted_values as $field_value) {
                                $terms = \Voxel\Term::query([
                                    'taxonomy' => $field->get_prop('taxonomy'),
                                    'hide_empty' => false,
                                    'orderby' => 'slug__in',
                                    'slug' => !empty($field_value) ? $field_value : [''],
                                ]);

                                // Include term labels AND synonyms if enabled
                                $values[] = join(' ', array_map(function($term) use ($include_synonyms) {
                                    return $this->get_term_with_synonyms($term, $include_synonyms);
                                }, $terms));
                            }
                        } elseif ($field->get_type() === 'location') {
                            foreach ($extracted_values as $field_value) {
                                $values[] = $field_value['address'] ?? null;
                            }
                        } else {
                            foreach ($extracted_values as $field_value) {
                                $values[] = $field_value;
                            }
                        }
                    }
                }
            } elseif ($field->get_type() === 'taxonomy') {
                // Include term labels AND synonyms if enabled
                $values[] = join(' ', array_map(function($term) use ($include_synonyms) {
                    return $this->get_term_with_synonyms($term, $include_synonyms);
                }, $field->get_value()));
            } elseif ($field->get_type() === 'location') {
                $values[] = $field->get_value()['address'] ?? null;
            } else {
                $values[] = $field->get_value();
            }
        }

        $data = join(' ', array_filter($values, '\is_string'));
        $data = wp_strip_all_tags($data);
        $data = $this->prepare_keywords_for_indexing($data);

        return [
            $this->db_key() => sprintf('\'%s\'', esc_sql($data)),
        ];
    }

    /**
     * Get term label with synonyms appended
     *
     * @param \Voxel\Term $term The term object
     * @param bool $include_synonyms Whether to include synonyms
     * @return string Term label with synonyms
     */
    protected function get_term_with_synonyms($term, bool $include_synonyms = true): string {
        $label = $term->get_label();

        if (!$include_synonyms) {
            return $label;
        }

        // Get synonyms from term meta
        $synonyms = get_term_meta($term->get_id(), 'vt_synonyms', true);

        if (!empty($synonyms)) {
            // Append synonyms to the label for indexing
            $label .= ' ' . $synonyms;
        }

        return $label;
    }
}
