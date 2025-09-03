<?php
/**
 * Functions Configuration with Translations
 * 
 * Returns the configuration array for available functions with translatable strings
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class Voxel_Toolkit_Functions_Config {
    
    /**
     * Get all available functions configuration
     */
    public static function get_functions() {
        return array(
            'auto_verify_posts' => array(
                'name' => __('Auto Verify Posts', 'voxel-toolkit'),
                'description' => __('Automatically mark posts as verified when submitted for selected post types.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Auto_Verify_Posts',
                'settings' => array(
                    'post_types' => array(
                        'label' => __('Post Types to Auto-Verify', 'voxel-toolkit'),
                        'type' => 'post_types',
                        'default' => array(),
                        'description' => __('Select which post types should be automatically marked as verified when submitted.', 'voxel-toolkit')
                    )
                )
            ),
            
            'admin_menu_hide' => array(
                'name' => __('Admin Menu Hide', 'voxel-toolkit'),
                'description' => __('Hide specific admin menu items for non-admin users to simplify the dashboard.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Admin_Menu_Hide',
                'settings' => array(
                    'hidden_menus' => array(
                        'label' => __('Menus to Hide', 'voxel-toolkit'),
                        'type' => 'checkboxes',
                        'default' => array(),
                        'options' => array(
                            'edit.php' => __('Posts', 'voxel-toolkit'),
                            'upload.php' => __('Media', 'voxel-toolkit'),
                            'edit.php?post_type=page' => __('Pages', 'voxel-toolkit'),
                            'edit-comments.php' => __('Comments', 'voxel-toolkit'),
                            'themes.php' => __('Appearance', 'voxel-toolkit'),
                            'plugins.php' => __('Plugins', 'voxel-toolkit'),
                            'users.php' => __('Users', 'voxel-toolkit'),
                            'tools.php' => __('Tools', 'voxel-toolkit'),
                            'options-general.php' => __('Settings', 'voxel-toolkit')
                        ),
                        'description' => __('Select which menu items to hide from non-admin users.', 'voxel-toolkit')
                    ),
                    'hide_for_roles' => array(
                        'label' => __('Hide for Roles', 'voxel-toolkit'),
                        'type' => 'roles',
                        'default' => array('editor', 'author', 'contributor'),
                        'description' => __('Select which user roles should have menus hidden.', 'voxel-toolkit')
                    )
                )
            ),
            
            'light_mode' => array(
                'name' => __('Light Mode', 'voxel-toolkit'),
                'description' => __('Enable a light color scheme for the Voxel theme with customizable colors.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Light_Mode',
                'settings' => array(
                    'color_scheme' => array(
                        'label' => __('Color Scheme', 'voxel-toolkit'),
                        'type' => 'select',
                        'default' => 'auto',
                        'options' => array(
                            'auto' => __('Auto (System Preference)', 'voxel-toolkit'),
                            'light' => __('Always Light', 'voxel-toolkit'),
                            'toggle' => __('User Toggle', 'voxel-toolkit')
                        ),
                        'description' => __('Choose how the light mode is activated.', 'voxel-toolkit')
                    ),
                    'custom_accent' => array(
                        'label' => __('Accent Color', 'voxel-toolkit'),
                        'type' => 'color',
                        'default' => '#2271b1',
                        'description' => __('Choose the primary accent color for light mode.', 'voxel-toolkit')
                    )
                )
            ),
            
            'auto_promotion' => array(
                'name' => __('Auto Promotion', 'voxel-toolkit'),
                'description' => __('Automatically promotes new posts by setting their priority when they are first published.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Auto_Promotion',
                'settings' => array(
                    'priority' => array(
                        'label' => __('Promotion Priority', 'voxel-toolkit'),
                        'type' => 'number',
                        'default' => 5,
                        'min' => 1,
                        'max' => 10,
                        'description' => __('Priority value to set when a post is promoted (1-10, where 10 is highest).', 'voxel-toolkit')
                    ),
                    'duration' => array(
                        'label' => __('Promotion Duration', 'voxel-toolkit'),
                        'type' => 'number',
                        'default' => 24,
                        'min' => 1,
                        'max' => 720,
                        'description' => __('How long the promotion should last.', 'voxel-toolkit')
                    ),
                    'duration_unit' => array(
                        'label' => __('Duration Unit', 'voxel-toolkit'),
                        'type' => 'select',
                        'default' => 'hours',
                        'options' => array(
                            'hours' => __('Hours', 'voxel-toolkit'),
                            'days' => __('Days', 'voxel-toolkit')
                        ),
                        'description' => __('Unit for the promotion duration.', 'voxel-toolkit')
                    ),
                    'post_types' => array(
                        'label' => __('Post Types', 'voxel-toolkit'),
                        'type' => 'post_types',
                        'default' => array(),
                        'description' => __('Select which post types to auto-promote when published.', 'voxel-toolkit')
                    )
                )
            ),
            
            'pre_approve_posts' => array(
                'name' => __('Pre-Approve Posts', 'voxel-toolkit'),
                'description' => __('Allow certain users to publish posts immediately without review based on verification status or manual approval.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Pre_Approve_Posts',
                'settings' => array(
                    'show_column' => array(
                        'label' => __('Show Admin Column', 'voxel-toolkit'),
                        'type' => 'checkbox',
                        'default' => true,
                        'description' => __('Show pre-approved status column in the Users admin list.', 'voxel-toolkit')
                    ),
                    'approve_verified' => array(
                        'label' => __('Auto-Approve Verified Users', 'voxel-toolkit'),
                        'type' => 'checkbox',
                        'default' => false,
                        'description' => __('Automatically approve posts from users with verified profiles.', 'voxel-toolkit')
                    ),
                    'approved_roles' => array(
                        'label' => __('Auto-Approve Roles', 'voxel-toolkit'),
                        'type' => 'roles',
                        'default' => array(),
                        'description' => __('Select user roles that should have posts automatically approved.', 'voxel-toolkit')
                    ),
                    'post_types' => array(
                        'label' => __('Applicable Post Types', 'voxel-toolkit'),
                        'type' => 'post_types',
                        'default' => array(),
                        'description' => __('Select which post types this auto-approval applies to. Leave empty for all post types.', 'voxel-toolkit')
                    )
                )
            )
        );
    }
    
    /**
     * Get widgets configuration
     */
    public static function get_widgets() {
        return array(
            'review_badges' => array(
                'name' => __('Review Badges', 'voxel-toolkit'),
                'description' => __('Display customizable review badges from your Voxel site on external websites.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Review_Badges'
            ),
            
            'user_switching' => array(
                'name' => __('User Switching', 'voxel-toolkit'),
                'description' => __('Allows administrators to temporarily switch to another user account for testing.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_User_Switching'
            ),
            
            'duplicate_post' => array(
                'name' => __('Duplicate Post', 'voxel-toolkit'),
                'description' => __('Adds the ability to duplicate Voxel posts with all their settings.', 'voxel-toolkit'),
                'enabled_by_default' => false,
                'class' => 'Voxel_Toolkit_Duplicate_Post'
            )
        );
    }
}