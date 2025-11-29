<?php
/**
 * Site Options Form Widget
 *
 * Frontend form widget for managing site options
 *
 * @package Voxel_Toolkit
 */

if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Site_Options_Form extends \Elementor\Widget_Base {

    public function __construct($data = [], $args = null) {
        parent::__construct($data, $args);

        // Handle form submission on template_redirect
        add_action('template_redirect', array($this, 'handle_form_submission_hook'));
    }

    public function handle_form_submission_hook() {
        if (isset($_POST['vt_site_options_nonce']) && wp_verify_nonce($_POST['vt_site_options_nonce'], 'vt_save_site_options')) {
            // Get configured fields
            $vt_settings = Voxel_Toolkit_Settings::instance();
            $config = $vt_settings->get_function_settings('options_page');
            $fields = isset($config['fields']) ? $config['fields'] : array();

            if (!empty($fields)) {
                $this->handle_form_submission($fields);

                // Get clean URL without query params
                $clean_url = strtok($_SERVER['REQUEST_URI'], '?');

                // Redirect to same page with success parameter
                wp_safe_redirect(add_query_arg('vt_saved', '1', $clean_url));
                exit;
            }
        }
    }

    public function get_name() {
        return 'voxel-toolkit-site-options-form';
    }

    public function get_title() {
        return __('Site Options Form', 'voxel-toolkit');
    }

    public function get_icon() {
        return 'eicon-form-horizontal';
    }

    public function get_categories() {
        return ['voxel-toolkit'];
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            [
                'label' => __('Content', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'form_title',
            [
                'label' => __('Form Title', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Site Options', 'voxel-toolkit'),
                'placeholder' => __('Enter form title', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'form_description',
            [
                'label' => __('Form Description', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXTAREA,
                'default' => __('Update site-wide settings below.', 'voxel-toolkit'),
                'placeholder' => __('Enter form description', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'submit_button_text',
            [
                'label' => __('Submit Button Text', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Save Options', 'voxel-toolkit'),
            ]
        );

        $this->add_control(
            'success_message',
            [
                'label' => __('Success Message', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => __('Options saved successfully!', 'voxel-toolkit'),
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => __('Style', 'voxel-toolkit'),
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        $this->add_control(
            'title_color',
            [
                'label' => __('Title Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-form-title' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'label_color',
            [
                'label' => __('Label Color', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-form-label' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'input_background',
            [
                'label' => __('Input Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-form-input' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'button_background',
            [
                'label' => __('Button Background', 'voxel-toolkit'),
                'type' => \Elementor\Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .vt-submit-button' => 'background-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        // Get configured fields
        $vt_settings = Voxel_Toolkit_Settings::instance();
        $config = $vt_settings->get_function_settings('options_page');
        $fields = isset($config['fields']) ? $config['fields'] : array();

        if (empty($fields)) {
            if (\Elementor\Plugin::$instance->editor->is_edit_mode()) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo __('No fields configured yet. Please configure fields in Site Options > Configure Fields.', 'voxel-toolkit');
                echo '</div>';
            }
            return;
        }

        // Check if we just saved
        $success = isset($_GET['vt_saved']) && $_GET['vt_saved'] == '1';

        ?>
        <div class="vt-site-options-form-widget">
            <?php if ($success): ?>
                <div class="vt-success-message">
                    <?php echo esc_html($settings['success_message']); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($settings['form_title'])): ?>
                <h2 class="vt-form-title"><?php echo esc_html($settings['form_title']); ?></h2>
            <?php endif; ?>

            <?php if (!empty($settings['form_description'])): ?>
                <p class="vt-form-description"><?php echo esc_html($settings['form_description']); ?></p>
            <?php endif; ?>

            <form method="post" class="vt-options-form">
                <?php wp_nonce_field('vt_save_site_options', 'vt_site_options_nonce'); ?>

                <div class="vt-form-fields">
                    <?php foreach ($fields as $field_name => $field_config): ?>
                        <div class="vt-form-row">
                            <label for="vt_option_<?php echo esc_attr($field_name); ?>" class="vt-form-label">
                                <?php echo esc_html($field_config['label']); ?>
                            </label>
                            <?php $this->render_field($field_name, $field_config); ?>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="vt-form-actions">
                    <button type="submit" class="vt-submit-button">
                        <?php echo esc_html($settings['submit_button_text']); ?>
                    </button>
                </div>
            </form>

            <style>
                .vt-site-options-form-widget {
                    max-width: 800px;
                    margin: 0 auto;
                }
                .vt-success-message {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    color: #155724;
                    padding: 15px 20px;
                    border-radius: 8px;
                    margin-bottom: 20px;
                    font-weight: 500;
                }
                .vt-form-title {
                    font-size: 28px;
                    font-weight: 600;
                    margin-bottom: 10px;
                    color: #1d2327;
                }
                .vt-form-description {
                    color: #646970;
                    margin-bottom: 30px;
                    font-size: 16px;
                }
                .vt-form-fields {
                    margin-bottom: 30px;
                }
                .vt-form-row {
                    margin-bottom: 25px;
                }
                .vt-form-label {
                    display: block;
                    font-weight: 600;
                    margin-bottom: 8px;
                    font-size: 15px;
                    color: #1d2327;
                }
                .vt-form-input {
                    width: 100%;
                    padding: 12px 16px;
                    border: 1px solid #e1e5e9;
                    border-radius: 8px;
                    font-size: 15px;
                    transition: border-color 0.2s ease;
                    background: #fff;
                }
                .vt-form-input:focus {
                    border-color: #1e3a5f;
                    outline: none;
                    box-shadow: 0 0 0 1px #1e3a5f;
                }
                .vt-form-input[type="textarea"] {
                    min-height: 100px;
                    resize: vertical;
                }
                .vt-form-actions {
                    margin-top: 30px;
                }
                .vt-submit-button {
                    background: #1e3a5f;
                    color: #fff;
                    border: none;
                    padding: 14px 32px;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    transition: all 0.2s ease;
                }
                .vt-submit-button:hover {
                    background: #2c5282;
                    transform: translateY(-1px);
                    box-shadow: 0 4px 8px rgba(30, 58, 95, 0.3);
                }
                .vt-image-upload-wrapper {
                    display: flex;
                    align-items: center;
                    gap: 15px;
                }
                .vt-image-preview {
                    width: 120px;
                    height: 120px;
                    border: 2px solid #e1e5e9;
                    border-radius: 8px;
                    overflow: hidden;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    background: #f8f9fa;
                }
                .vt-image-preview img {
                    max-width: 100%;
                    max-height: 100%;
                    object-fit: cover;
                }
                .vt-image-preview.empty {
                    color: #a7aaad;
                    font-size: 13px;
                }
                .vt-image-button {
                    background: #fff;
                    border: 1px solid #e1e5e9;
                    padding: 10px 20px;
                    border-radius: 6px;
                    cursor: pointer;
                    font-size: 14px;
                    transition: all 0.2s ease;
                }
                .vt-image-button:hover {
                    background: #f8f9fa;
                    border-color: #1e3a5f;
                }
            </style>

            <script>
            jQuery(document).ready(function($) {
                // Handle image selection
                $('.vt-select-image').on('click', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var fieldName = button.data('field');

                    var frame = wp.media({
                        title: '<?php _e('Select Image', 'voxel-toolkit'); ?>',
                        button: {
                            text: '<?php _e('Use this image', 'voxel-toolkit'); ?>'
                        },
                        multiple: false
                    });

                    frame.on('select', function() {
                        var attachment = frame.state().get('selection').first().toJSON();
                        $('#vt_option_' + fieldName).val(attachment.id);
                        $('#vt_preview_' + fieldName).removeClass('empty').html('<img src="' + attachment.url + '" alt="">');
                        button.text('<?php _e('Change Image', 'voxel-toolkit'); ?>');

                        // Add remove button if it doesn't exist
                        if (!button.siblings('.vt-remove-image').length) {
                            button.after('<button type="button" class="vt-image-button vt-remove-image" data-field="' + fieldName + '"><?php _e('Remove', 'voxel-toolkit'); ?></button>');
                        }
                    });

                    frame.open();
                });

                // Handle image removal
                $(document).on('click', '.vt-remove-image', function(e) {
                    e.preventDefault();
                    var button = $(this);
                    var fieldName = button.data('field');

                    $('#vt_option_' + fieldName).val('');
                    $('#vt_preview_' + fieldName).addClass('empty').html('<?php _e('No image', 'voxel-toolkit'); ?>');
                    button.siblings('.vt-select-image').text('<?php _e('Select Image', 'voxel-toolkit'); ?>');
                    button.remove();
                });
            });
            </script>
        </div>
        <?php
    }

    private function render_field($field_name, $field_config) {
        $option_name = 'voxel_options_' . $field_name;
        $value = get_option($option_name, $field_config['default']);
        $type = $field_config['type'];

        switch ($type) {
            case 'text':
                ?>
                <input type="text"
                       id="vt_option_<?php echo esc_attr($field_name); ?>"
                       name="vt_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_attr($value); ?>"
                       class="vt-form-input"
                       placeholder="<?php echo esc_attr($field_config['label']); ?>" />
                <?php
                break;

            case 'textarea':
                ?>
                <textarea id="vt_option_<?php echo esc_attr($field_name); ?>"
                          name="vt_options[<?php echo esc_attr($field_name); ?>]"
                          class="vt-form-input"
                          rows="5"
                          placeholder="<?php echo esc_attr($field_config['label']); ?>"><?php echo esc_textarea($value); ?></textarea>
                <?php
                break;

            case 'number':
                ?>
                <input type="number"
                       id="vt_option_<?php echo esc_attr($field_name); ?>"
                       name="vt_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_attr($value); ?>"
                       class="vt-form-input"
                       placeholder="<?php echo esc_attr($field_config['label']); ?>" />
                <?php
                break;

            case 'url':
                ?>
                <input type="url"
                       id="vt_option_<?php echo esc_attr($field_name); ?>"
                       name="vt_options[<?php echo esc_attr($field_name); ?>]"
                       value="<?php echo esc_url($value); ?>"
                       class="vt-form-input"
                       placeholder="https://" />
                <?php
                break;

            case 'image':
                $image_id = intval($value);
                $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'medium') : '';
                ?>
                <div class="vt-image-upload-wrapper">
                    <div class="vt-image-preview <?php echo $image_url ? '' : 'empty'; ?>" id="vt_preview_<?php echo esc_attr($field_name); ?>">
                        <?php if ($image_url): ?>
                            <img src="<?php echo esc_url($image_url); ?>" alt="">
                        <?php else: ?>
                            <?php _e('No image', 'voxel-toolkit'); ?>
                        <?php endif; ?>
                    </div>
                    <div>
                        <input type="hidden"
                               id="vt_option_<?php echo esc_attr($field_name); ?>"
                               name="vt_options[<?php echo esc_attr($field_name); ?>]"
                               value="<?php echo esc_attr($image_id); ?>"
                               class="vt-image-id" />
                        <button type="button" class="vt-image-button vt-select-image" data-field="<?php echo esc_attr($field_name); ?>">
                            <?php echo $image_id ? __('Change Image', 'voxel-toolkit') : __('Select Image', 'voxel-toolkit'); ?>
                        </button>
                        <?php if ($image_id): ?>
                            <button type="button" class="vt-image-button vt-remove-image" data-field="<?php echo esc_attr($field_name); ?>">
                                <?php _e('Remove', 'voxel-toolkit'); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php
                break;
        }
    }

    private function handle_form_submission($fields) {
        if (!isset($_POST['vt_options'])) {
            return;
        }

        foreach ($fields as $field_name => $field_config) {
            $option_name = 'voxel_options_' . $field_name;
            $value = isset($_POST['vt_options'][$field_name]) ? $_POST['vt_options'][$field_name] : '';

            // Sanitize based on field type
            $sanitized_value = $this->sanitize_field_value($value, $field_config['type']);

            // Update option
            update_option($option_name, $sanitized_value, true);
        }
    }

    private function sanitize_field_value($value, $type) {
        switch ($type) {
            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            case 'number':
                return intval($value);

            case 'url':
                return esc_url_raw($value);

            case 'image':
                return intval($value);

            default:
                return sanitize_text_field($value);
        }
    }
}
