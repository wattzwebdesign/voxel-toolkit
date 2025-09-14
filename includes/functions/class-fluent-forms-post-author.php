<?php
/**
 * Fluent Forms Post Author Integration
 * 
 * Adds a "Voxel Post Author" email field to Fluent Forms that automatically
 * populates with the post author's email when the form is embedded on a post.
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Fluent_Forms_Post_Author {
    
    private static $instance = null;
    private $initialized = false;
    
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
    private function __construct() {
        // Only initialize if Fluent Forms is active
        if ($this->is_fluent_forms_active()) {
            // Initialize immediately since Fluent Forms is already loaded
            $this->init();
            
            // Also add hooks in case we need them later
            add_action('fluentform/loaded', array($this, 'init'));
            add_action('init', array($this, 'init'), 20);
        }
    }
    
    /**
     * Check if Fluent Forms (free version) is active
     */
    private function is_fluent_forms_active() {
        if (!function_exists('is_plugin_active')) {
            include_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        
        return is_plugin_active('fluentform/fluentform.php') || 
               class_exists('FluentForm\Framework\Foundation\Application') ||
               defined('FLUENTFORM');
    }
    
    /**
     * Initialize the integration
     */
    public function init() {
        // Prevent multiple initializations
        if ($this->initialized) {
            return;
        }
        
        $this->initialized = true;
        
        // Add custom field to form builder
        add_filter('fluentform/editor_components', array($this, 'add_voxel_post_author_direct'), 10, 2);
        
        // Add field rendering for input_email fields with our specific name
        add_filter('fluentform/rendering_field_data_input_email', array($this, 'modify_field_data'), 10, 2);
        add_filter('fluentform/rendering_field_html_input_email', array($this, 'render_field_html'), 10, 3);
        
        // Add custom settings for our field
        add_filter('fluentform/editor_element_settings_placement', array($this, 'add_custom_settings'), 10, 2);
        add_filter('fluentform/editor_element_customization_settings', array($this, 'add_custom_settings'), 10, 2);
        
        // Pre-populate field with post author email
        add_filter('fluentform/rendering_form', array($this, 'populate_post_author_email'), 10, 2);
        
        // Add styles for the field
        add_action('wp_head', array($this, 'add_field_styles'));
    }
    
    /**
     * Add Voxel Post Author field directly via filter
     */
    public function add_voxel_post_author_direct($components, $formId) {
        // Add our custom field using a structure similar to built-in fields
        $voxel_field = array(
            'index' => 25,
            'element' => 'input_email', // Use existing email element type
            'attributes' => array(
                'name' => 'voxel_post_author',
                'class' => '',
                'value' => '',
                'type' => 'email',
                'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit')
            ),
            'settings' => array(
                'container_class' => '',
                'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit'),
                'label' => __('Voxel Post Author', 'voxel-toolkit'),
                'label_placement' => '',
                'help_message' => __('This field automatically populates with the email of the post author where this form is embedded.', 'voxel-toolkit'),
                'admin_field_label' => '',
                'validation_rules' => array(
                    'required' => array(
                        'value' => false,
                        'message' => __('This field is required.', 'voxel-toolkit')
                    ),
                    'email' => array(
                        'value' => true,
                        'message' => __('Please provide a valid email address.', 'voxel-toolkit')
                    )
                ),
                'conditional_logics' => array(),
                'voxel_hidden_field' => array(
                    'value' => false,
                    'label' => __('Hidden Field', 'voxel-toolkit'),
                    'help_text' => __('Hide this field from frontend but keep it functional for notifications', 'voxel-toolkit')
                )
            ),
            'editor_options' => array(
                'title' => __('Voxel Post Author', 'voxel-toolkit'),
                'icon_class' => 'ff-edit-email',
                'template' => 'inputText'
            )
        );
        
        $components['advanced'][] = $voxel_field;
        
        return $components;
    }
    
    /**
     * Modify field data before rendering
     */
    public function modify_field_data($data, $form) {
        global $post;
        
        // Only modify our specific field
        if (!isset($data['attributes']['name']) || $data['attributes']['name'] !== 'voxel_post_author') {
            return $data;
        }
        
        // Auto-populate with post author email if on a post
        if (is_singular() && $post && isset($post->post_author)) {
            $author_email = get_the_author_meta('user_email', $post->post_author);
            if ($author_email) {
                $data['attributes']['value'] = $author_email;
                $data['attributes']['readonly'] = true;
                $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' voxel-post-author-field';
                $data['settings']['placeholder'] = sprintf(
                    __('Auto-populated: %s', 'voxel-toolkit'), 
                    $author_email
                );
            }
        }
        
        return $data;
    }
    
    /**
     * Render the field HTML
     */
    public function render_field_html($html, $data, $form) {
        // Only modify our specific field
        if (!isset($data['attributes']['name']) || $data['attributes']['name'] !== 'voxel_post_author') {
            return $html;
        }
        
        $elementName = $data['element'];
        $data = apply_filters('fluentform/rendering_field_data_' . $elementName, $data, $form);
        
        $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' ff-el-form-control';
        $data['attributes']['id'] = $this->makeElementId($data, $form);
        $data['attributes']['type'] = 'email';
        
        // Add tab index if available (skip if class doesn't exist)
        if (class_exists('\FluentForm\Framework\Helpers\Helper') && method_exists('\FluentForm\Framework\Helpers\Helper', 'getNextTabIndex')) {
            if ($tabIndex = \FluentForm\Framework\Helpers\Helper::getNextTabIndex()) {
                $data['attributes']['tabindex'] = $tabIndex;
            }
        }
        
        $elMarkup = "<input " . $this->buildAttributes($data['attributes']) . ">";
        
        $html = $this->buildElementMarkup($elMarkup, $data, $form);
        return $html;
    }
    
    /**
     * Pre-populate the field with post author email (fallback method)
     */
    public function populate_post_author_email($form, $form_vars = null) {
        global $post;
        
        // Only proceed if we're on a single post/page and have a post object
        if (!is_singular() || !$post || !isset($post->post_author)) {
            return $form;
        }
        
        // Get the post author's email
        $author_email = get_the_author_meta('user_email', $post->post_author);
        
        if (!$author_email) {
            return $form;
        }
        
        // Find Voxel Post Author fields in the form and pre-populate them
        if (isset($form->fields) && isset($form->fields['fields'])) {
            foreach ($form->fields['fields'] as $key => $field) {
                if (isset($field['element']) && $field['element'] === 'voxel_post_author') {
                    $form->fields['fields'][$key]['attributes']['value'] = $author_email;
                    $form->fields['fields'][$key]['attributes']['readonly'] = true;
                    
                    // Add styling class
                    $existing_class = isset($field['attributes']['class']) ? $field['attributes']['class'] : '';
                    $form->fields['fields'][$key]['attributes']['class'] = $existing_class . ' voxel-post-author-field';
                    
                    // Update the placeholder
                    $form->fields['fields'][$key]['settings']['placeholder'] = sprintf(
                        __('Auto-populated: %s', 'voxel-toolkit'), 
                        $author_email
                    );
                }
            }
        }
        
        return $form;
    }
    
    /**
     * Add custom styles for the Voxel Post Author field
     */
    public function add_field_styles() {
        ?>
        <style type="text/css">
        .voxel-post-author-field {
            background-color: #f8f9fa !important;
            border: 1px solid #dee2e6 !important;
            color: #6c757d !important;
            cursor: not-allowed !important;
        }
        .voxel-post-author-field:focus {
            box-shadow: none !important;
            border-color: #dee2e6 !important;
        }
        .voxel-hidden-field {
            display: none !important;
        }
        </style>
        <?php
    }
    
    /**
     * Helper method to make element ID
     */
    private function makeElementId($data, $form) {
        $formId = $form->id;
        $elementName = $data['element'];
        return "ff_{$formId}_{$elementName}";
    }
    
    /**
     * Helper method to build attributes string
     */
    private function buildAttributes($attributes) {
        $atts = '';
        foreach ($attributes as $key => $value) {
            if ($value !== '' && $value !== null) {
                $atts .= $key . '="' . esc_attr($value) . '" ';
            }
        }
        return trim($atts);
    }
    
    /**
     * Helper method to build element markup
     */
    private function buildElementMarkup($elMarkup, $data, $form) {
        // Check if this field should be hidden
        $isHidden = isset($data['settings']['voxel_hidden_field']['value']) && 
                   $data['settings']['voxel_hidden_field']['value'] === true;
        
        $hasConditions = $this->hasConditions($data) ? 'has-conditions ' : '';
        $cls = $hasConditions;
        $cls .= 'ff-field_container ff-name-' . $data['attributes']['name'] . ' ff-el-group ff-el-form-control';
        
        // Add hidden class if field should be hidden
        if ($isHidden) {
            $cls .= ' voxel-hidden-field';
        }
        
        $atts = array(
            'class' => $cls,
            'data-name' => $data['attributes']['name'],
        );
        
        if ($hasConditions) {
            $atts['data-condition_settings'] = json_encode($data['settings']['conditional_logics']);
        }
        
        $atts = $this->buildAttributes($atts);
        
        $label = '';
        if (!empty($data['settings']['label']) && !$isHidden) {
            $label = '<div class="ff-el-input--label"><label for="' . $data['attributes']['id'] . '">' . 
                     wp_kses_post($data['settings']['label']) . '</label></div>';
        }
        
        $help = '';
        if (!empty($data['settings']['help_message']) && !$isHidden) {
            $help = '<div class="ff-el-help-message"><div class="ff-el-help-content">' . 
                    wp_kses_post($data['settings']['help_message']) . '</div></div>';
        }
        
        // If hidden, make the input field hidden but keep it in the form for functionality
        if ($isHidden) {
            $elMarkup = str_replace('type="email"', 'type="hidden"', $elMarkup);
        }
        
        return '<div ' . $atts . '>' . $label . '<div class="ff-el-input--content"><div class="ff-el-form-control">' . 
               $elMarkup . '</div></div>' . $help . '</div>';
    }
    
    /**
     * Add custom settings for our field
     */
    public function add_custom_settings($placement_settings, $form_id = null) {
        // Only add settings for our specific field
        if (!isset($_REQUEST['element']) || $_REQUEST['element'] !== 'input_email') {
            return $placement_settings;
        }
        
        // Check if this is our voxel post author field
        if (!isset($_REQUEST['field_name']) || $_REQUEST['field_name'] !== 'voxel_post_author') {
            return $placement_settings;
        }
        
        // Add our custom hidden field setting
        $placement_settings['voxel_hidden_field'] = array(
            'template' => 'radio',
            'label' => __('Hidden Field', 'voxel-toolkit'),
            'help_text' => __('Hide this field from frontend but keep it functional for notifications', 'voxel-toolkit'),
            'options' => array(
                array(
                    'value' => false,
                    'label' => __('Show Field', 'voxel-toolkit')
                ),
                array(
                    'value' => true,
                    'label' => __('Hide Field', 'voxel-toolkit')
                )
            )
        );
        
        return $placement_settings;
    }
    
    /**
     * Helper method to check if field has conditions
     */
    private function hasConditions($data) {
        return !empty($data['settings']['conditional_logics']) && 
               is_array($data['settings']['conditional_logics']) && 
               !empty($data['settings']['conditional_logics']['conditions']);
    }
}

/**
 * Voxel Post Author Field Class
 * 
 * Extends BaseFieldManager to create a custom field for Fluent Forms
 */
if (class_exists('\FluentForm\App\Services\FormBuilder\BaseFieldManager')) {
    
    class Voxel_Post_Author_Field extends \FluentForm\App\Services\FormBuilder\BaseFieldManager {
        
        public function __construct() {
            error_log('Voxel Toolkit: Voxel_Post_Author_Field constructor called');
            parent::__construct(
                'voxel_post_author',
                'Voxel Post Author',
                ['email', 'author', 'post'],
                'advanced'
            );
            error_log('Voxel Toolkit: Voxel_Post_Author_Field initialized successfully');
        }
        
        /**
         * Get the component for the form editor
         */
        public function getComponent() {
            error_log('Voxel Toolkit: getComponent() called for Voxel_Post_Author_Field');
            return array(
                'index' => 25,
                'element' => $this->key,
                'attributes' => array(
                    'name' => $this->key,
                    'class' => '',
                    'value' => '',
                    'type' => 'email',
                    'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit')
                ),
                'settings' => array(
                    'container_class' => '',
                    'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit'),
                    'label' => __('Voxel Post Author', 'voxel-toolkit'),
                    'label_placement' => '',
                    'help_message' => __('This field automatically populates with the email of the post author where this form is embedded.', 'voxel-toolkit'),
                    'admin_field_label' => '',
                    'validation_rules' => array(
                        'required' => array(
                            'value' => false,
                            'message' => __('This field is required.', 'voxel-toolkit')
                        ),
                        'email' => array(
                            'value' => true,
                            'message' => __('Please provide a valid email address.', 'voxel-toolkit')
                        )
                    ),
                    'conditional_logics' => array()
                ),
                'editor_options' => array(
                    'title' => __('Voxel Post Author', 'voxel-toolkit'),
                    'icon_class' => 'ff-edit-email',
                    'template' => 'inputText'
                )
            );
        }
        
        /**
         * Get general editor elements
         */
        public function getGeneralEditorElements() {
            return [
                'label',
                'admin_field_label',
                'placeholder',
                'label_placement',
                'validation_rules'
            ];
        }
        
        /**
         * Get advanced editor elements
         */
        public function getAdvancedEditorElements() {
            return [
                'name',
                'help_message',
                'container_class',
                'class',
                'conditional_logics'
            ];
        }
        
        /**
         * Render the field
         */
        public function render($data, $form) {
            global $post;
            
            // Auto-populate with post author email if on a post
            if (is_singular() && $post && isset($post->post_author)) {
                $author_email = get_the_author_meta('user_email', $post->post_author);
                if ($author_email) {
                    $data['attributes']['value'] = $author_email;
                    $data['attributes']['readonly'] = true;
                    $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' voxel-post-author-field';
                    $data['settings']['placeholder'] = sprintf(
                        __('Auto-populated: %s', 'voxel-toolkit'), 
                        $author_email
                    );
                }
            }
            
            $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' ff-el-form-control';
            $data['attributes']['id'] = $this->makeElementId($data, $form);
            $data['attributes']['type'] = 'email';
            
            // Add tab index if available (skip if class doesn't exist)
            if (class_exists('\FluentForm\Framework\Helpers\Helper') && method_exists('\FluentForm\Framework\Helpers\Helper', 'getNextTabIndex')) {
                if ($tabIndex = \FluentForm\Framework\Helpers\Helper::getNextTabIndex()) {
                    $data['attributes']['tabindex'] = $tabIndex;
                }
            }
            
            $elMarkup = "<input " . $this->buildAttributes($data['attributes']) . ">";
            
            echo $this->buildElementMarkup($elMarkup, $data, $form);
        }
    }
}

/**
 * Alternative Voxel Post Author Field Class (for older Fluent Forms versions)
 */
if (class_exists('\FluentForm\App\Modules\Component\BaseFieldManager')) {
    
    class Voxel_Post_Author_Field_Alt extends \FluentForm\App\Modules\Component\BaseFieldManager {
        
        public function __construct() {
            parent::__construct(
                'voxel_post_author',
                'Voxel Post Author',
                ['email', 'author', 'post'],
                'advanced'
            );
        }
        
        /**
         * Get the component for the form editor
         */
        public function getComponent() {
            return array(
                'index' => 25,
                'element' => $this->key,
                'attributes' => array(
                    'name' => $this->key,
                    'class' => '',
                    'value' => '',
                    'type' => 'email',
                    'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit')
                ),
                'settings' => array(
                    'container_class' => '',
                    'placeholder' => __('Post author email will be populated automatically', 'voxel-toolkit'),
                    'label' => __('Voxel Post Author', 'voxel-toolkit'),
                    'label_placement' => '',
                    'help_message' => __('This field automatically populates with the email of the post author where this form is embedded.', 'voxel-toolkit'),
                    'admin_field_label' => '',
                    'validation_rules' => array(
                        'required' => array(
                            'value' => false,
                            'message' => __('This field is required.', 'voxel-toolkit')
                        ),
                        'email' => array(
                            'value' => true,
                            'message' => __('Please provide a valid email address.', 'voxel-toolkit')
                        )
                    ),
                    'conditional_logics' => array()
                ),
                'editor_options' => array(
                    'title' => __('Voxel Post Author', 'voxel-toolkit'),
                    'icon_class' => 'ff-edit-email',
                    'template' => 'inputText'
                )
            );
        }
        
        /**
         * Get general editor elements
         */
        public function getGeneralEditorElements() {
            return [
                'label',
                'admin_field_label',
                'placeholder',
                'label_placement',
                'validation_rules'
            ];
        }
        
        /**
         * Get advanced editor elements
         */
        public function getAdvancedEditorElements() {
            return [
                'name',
                'help_message',
                'container_class',
                'class',
                'conditional_logics'
            ];
        }
        
        /**
         * Render the field
         */
        public function render($data, $form) {
            global $post;
            
            // Auto-populate with post author email if on a post
            if (is_singular() && $post && isset($post->post_author)) {
                $author_email = get_the_author_meta('user_email', $post->post_author);
                if ($author_email) {
                    $data['attributes']['value'] = $author_email;
                    $data['attributes']['readonly'] = true;
                    $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' voxel-post-author-field';
                    $data['settings']['placeholder'] = sprintf(
                        __('Auto-populated: %s', 'voxel-toolkit'), 
                        $author_email
                    );
                }
            }
            
            $data['attributes']['class'] = (@$data['attributes']['class'] ?: '') . ' ff-el-form-control';
            $data['attributes']['id'] = $this->makeElementId($data, $form);
            $data['attributes']['type'] = 'email';
            
            // Add tab index if available (skip if class doesn't exist)
            if (class_exists('\FluentForm\Framework\Helpers\Helper') && method_exists('\FluentForm\Framework\Helpers\Helper', 'getNextTabIndex')) {
                if ($tabIndex = \FluentForm\Framework\Helpers\Helper::getNextTabIndex()) {
                    $data['attributes']['tabindex'] = $tabIndex;
                }
            }
            
            $elMarkup = "<input " . $this->buildAttributes($data['attributes']) . ">";
            
            echo $this->buildElementMarkup($elMarkup, $data, $form);
        }
    }
}