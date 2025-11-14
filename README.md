# Voxel Toolkit

A comprehensive WordPress plugin toolkit for extending Voxel theme functionality with toggleable features and customizable settings.

## Description

Voxel Toolkit provides a modular approach to extending your Voxel theme with additional functionality. Each function can be enabled or disabled independently, and comes with its own settings page for easy customization.

## Features

### Auto Verify Posts
- Automatically marks posts as verified when submitted
- Configurable per post type
- Uses Voxel theme hooks for seamless integration
- Multiple verification methods for maximum compatibility

## Installation

1. Download the plugin files
2. Upload to your `/wp-content/plugins/` directory
3. Activate the plugin through the 'Plugins' menu in WordPress
4. Navigate to 'Voxel Toolkit' in the admin menu to configure functions

## Requirements

- WordPress 5.0 or higher
- PHP 8.1 or higher
- Voxel theme (active)

## Usage

### Enabling Functions

1. Go to **Voxel Toolkit** > **Functions** in your WordPress admin
2. Toggle the switch next to any function to enable/disable it
3. Click **Configure** to access function-specific settings
4. Save your settings

### Auto Verify Posts

1. Enable the "Auto Verify Posts" function
2. Go to **Voxel Toolkit** > **Settings**
3. Select which post types should be automatically verified
4. Save settings

The plugin will now automatically mark posts as verified when they are submitted for the selected post types.

## Hooks and Filters

The plugin provides several hooks for developers:

### Actions

- `voxel_toolkit/settings_updated` - Fired when plugin settings are updated
- `voxel_toolkit/function_initialized/{function_key}` - Fired when a function is initialized
- `voxel_toolkit/function_deinitialized/{function_key}` - Fired when a function is deinitialized
- `voxel_toolkit/post_auto_verified` - Fired when a post is auto-verified

### Filters

- `voxel_toolkit/available_functions` - Filter available functions
- `voxel_toolkit/sanitize_function_settings/{function_key}` - Filter function settings sanitization

## File Structure

```
voxel-toolkit/
├── voxel-toolkit.php           # Main plugin file
├── README.md                   # Documentation
├── assets/                     # Plugin assets
│   ├── css/
│   │   └── admin.css          # Admin styles
│   └── js/
│       └── admin.js           # Admin JavaScript
└── includes/                   # Plugin classes
    ├── class-settings.php      # Settings management
    ├── class-functions.php     # Functions manager
    ├── admin/
    │   └── class-admin.php     # Admin interface
    └── functions/
        └── class-auto-verify-posts.php  # Auto verify functionality
```

## Extending the Plugin

### Adding New Functions

1. Create a new class in `includes/functions/`
2. Register the function in the `register_functions()` method of `Voxel_Toolkit_Functions`
3. Implement the settings callback if needed
4. Add sanitization rules in `Voxel_Toolkit_Admin::sanitize_options()`

### Example Function Registration

```php
$this->available_functions['my_new_function'] = array(
    'name' => __('My New Function', 'voxel-toolkit'),
    'description' => __('Description of what this function does.', 'voxel-toolkit'),
    'class' => 'Voxel_Toolkit_My_New_Function',
    'file' => 'functions/class-my-new-function.php',
    'settings_callback' => array($this, 'render_my_function_settings'),
    'version' => '1.0.1'
);
```

## Compatibility

This plugin is specifically designed for the Voxel theme and uses Voxel's hooks and actions for integration. It will not function without the Voxel theme active.

### Tested Voxel Hooks

The auto-verify functionality uses multiple Voxel hooks for maximum compatibility:

- `voxel/admin/save_post`
- `voxel/create-post-validation`
- Standard WordPress hooks as fallbacks

## Support

For support and feature requests, please contact the plugin developer.

## Changelog

### 1.0.1
- Fixed duplicate plugin registration issue
- Removed duplicate plugin files

### 1.0.0
- Initial release
- Auto Verify Posts functionality
- Admin interface with toggleable functions
- Settings management system
- Voxel theme integration

## License

GPL v2 or later