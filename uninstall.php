<?php
/**
 * Voxel Toolkit Uninstall Script
 * 
 * Cleans up plugin data when uninstalled
 */

// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

/**
 * Clean up plugin data
 */
function voxel_toolkit_uninstall() {
    // Remove plugin options
    delete_option('voxel_toolkit_options');
    
    // Remove any scheduled events
    wp_clear_scheduled_hook('voxel_toolkit_delayed_verify');
    
    // Clean up any post meta that might have been added
    // Note: We don't remove verification meta as it might be used by other plugins/processes
    
    // Remove any custom tables if they were created (none in current version)
    
    // Clear any cached data
    wp_cache_flush();
    
    // Remove plugin capabilities if any were added (none in current version)
    
    // Log uninstall for debugging
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Voxel Toolkit: Plugin uninstalled and data cleaned up');
    }
}

// Execute cleanup
voxel_toolkit_uninstall();