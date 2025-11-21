- Custom Post Field Implementation Pattern (for future reference)

  File Structure:

  1. Field file: includes/post-fields/class-{field-name}-field.php
    - Contains TWO classes:
        - Voxel_Toolkit_{FieldName}_Field - Manager class (handles hooks,
  AJAX, etc.)
      - Voxel_Toolkit_{FieldName}_Field_Type - Actual field type class
  (extends \Voxel\Post_Types\Fields\Base_Post_Field)

  Registration in includes/class-post-fields.php:

  $this->available_post_fields = array(
      'field_key' => array(
          'name' => __('Field Name (VT)', 'voxel-toolkit'),
          'description' => __('Description', 'voxel-toolkit'),
          'class' => 'Voxel_Toolkit_{FieldName}_Field',
          'file' => 'post-fields/class-{field-name}-field.php',
          'icon' => 'dashicons-icon-name',
          'required_widgets' => array(), // Optional: auto-enable widgets
      ),
  );

  Registration in main voxel-toolkit.php:

  Add conditional registration in register_poll_field_if_enabled() method (or
  create similar for new field):
  // In early_init() method:
  add_filter('voxel/field-types', array($this,
  'register_{field}_field_if_enabled'), 10);

  // Create method:
  public function register_{field}_field_if_enabled($fields) {
      // Load settings if needed
      if (!class_exists('Voxel_Toolkit_Settings')) {
          require_once VOXEL_TOOLKIT_PLUGIN_DIR .
  'includes/class-settings.php';
      }

      // Check if enabled
      if (!Voxel_Toolkit_Settings::instance()->is_function_enabled('post_field
  _{field_key}')) {
          return $fields;
      }

      // Check Base_Post_Field exists
      if (!class_exists('\Voxel\Post_Types\Fields\Base_Post_Field')) {
          return $fields;
      }

      // Register
      $fields['{field-type-key}'] = '\Voxel_Toolkit_{FieldName}_Field_Type';
      return $fields;
  }

  Key Points:

  1. Always load the field file (in load_post_field_file) - prevents crashes
  2. Only initialize manager class when enabled (in init_post_field) -
  respects settings
  3. Register with Voxel EARLY (in main plugin file via early_init) - proper
  timing
  4. Conditional registration - checks if feature is enabled before
  registering

  This ensures: ✅ No crashes, ✅ Conditional visibility, ✅ Proper timing!