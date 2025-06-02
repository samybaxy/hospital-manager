<?php
/**
 * WordPress Bootstrap Finder
 * 
 * This script attempts to find and load WordPress from various common locations.
 */

// Display errors during development
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/**
 * Find WordPress installation
 */
function find_wordpress() {
    // Current directory
    $current_dir = __DIR__;
    
    // Attempt to find wp-load.php by traversing up directories
    $max_levels = 10; // Prevent infinite loop
    $dir = $current_dir;
    
    for ($i = 0; $i < $max_levels; $i++) {
        // Check if wp-load.php exists in the current directory
        if (file_exists($dir . '/wp-load.php')) {
            return $dir . '/wp-load.php';
        }
        
        // Go up one directory
        $parent_dir = dirname($dir);
        
        // If we're already at the root, stop
        if ($parent_dir === $dir) {
            break;
        }
        
        $dir = $parent_dir;
    }
    
    // Check common paths for WordPress installations
    $common_paths = [
        '/var/www/html/wp-load.php',
        '/home/sites/wordpress/wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
        dirname($_SERVER['DOCUMENT_ROOT']) . '/wp-load.php'
    ];
    
    foreach ($common_paths as $path) {
        if (file_exists($path)) {
            return $path;
        }
    }
    
    return false;
}

// Find WordPress
$wp_load_path = find_wordpress();

if (!$wp_load_path) {
    die("Error: WordPress installation not found. Please specify the path manually.");
}

echo "WordPress found at: $wp_load_path\n";

// Load WordPress
require_once $wp_load_path;

// Verify WordPress is loaded correctly
if (!function_exists('get_bloginfo')) {
    die("Error: WordPress failed to load properly.");
}

echo "WordPress loaded successfully.\n";

// Verify database connection
global $wpdb;
if (!isset($wpdb)) {
    die("Error: WordPress database connection not available.");
}

echo "Database connection established.\n";

// Return true to indicate successful bootstrap
return true;