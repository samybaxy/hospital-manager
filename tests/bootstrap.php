<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Hospital_Manager
 */

// Composer autoloader must be loaded before WP's bootstrap.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// Load the PHPUnit Polyfills for WordPress testing
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/src/' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Forward custom PHPUnit Polyfills configuration to PHPUnit bootstrap file.
// $_phpunit_polyfills_path = getenv( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' );
// if ( false !== $_phpunit_polyfills_path ) {
// 	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_phpunit_polyfills_path );
// }

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

// Give access to tests_add_filter() function.
require_once "{$_tests_dir}/includes/functions.php";

/**
 * Manually load the plugin being tested.
 */
function _manually_load_plugin() {
    // Define a constant to let the plugin know it's being tested
    if (!defined('RUNNING_PHPUNIT_TESTS')) {
        define('RUNNING_PHPUNIT_TESTS', true);
    }
    
    // Load the plugin file
	require dirname( dirname( __FILE__ ) ) . '/hospital-manager.php';
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";
