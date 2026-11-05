<?php
// Command-line only: this script changes or wipes data and must never run from a web request.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
/**
 * PHPUnit bootstrap file.
 *
 * @package Hospital_Manager
 */

// Composer autoloader must be loaded before WP's bootstrap.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// getallheaders() only exists under a web SAPI, but auth code reads request
// headers through it. Same result, built from $_SERVER['HTTP_*'].
if ( ! function_exists( 'getallheaders' ) ) {
	function getallheaders() {
		$headers = [];
		foreach ( $_SERVER as $key => $value ) {
			if ( strpos( $key, 'HTTP_' ) === 0 ) {
				$name             = str_replace( ' ', '-', ucwords( strtolower( str_replace( '_', ' ', substr( $key, 5 ) ) ) ) );
				// Keep the casing clients actually send (X-WP-Nonce, not X-Wp-Nonce).
				$name             = preg_replace( '/^X-Wp-/', 'X-WP-', $name );
				$headers[ $name ] = $value;
			}
		}
		return $headers;
	}
}

// Load the PHPUnit Polyfills for WordPress testing
define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills/src/' );

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

// Check if the WordPress test directory exists and install it if it doesn't
if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
    echo "WordPress tests directory not found. Attempting to install WordPress test environment..." . PHP_EOL;
    
    $installer_script = dirname( dirname( __FILE__ ) ) . '/bin/install-wp-tests.sh';
    
    if (!file_exists($installer_script)) {
        echo "Error: Could not find installer script at {$installer_script}" . PHP_EOL;
        exit(1);
    }
    
    // Get test database credentials - you may need to customize these parameters based on your setup
    $db_name = getenv('WP_TEST_DB_NAME') ?: 'wordpress_test';
    $db_user = getenv('WP_TEST_DB_USER') ?: 'root';
    $db_pass = getenv('WP_TEST_DB_PASS') ?: 'root';
    
    // DB host: explicit WP_TEST_DB_HOST wins; otherwise use the MySQL socket the
    // running PHP is configured with (Local by Flywheel sets mysqli.default_socket
    // per site, so this follows whichever site's PHP runs the tests).
    $db_host = getenv('WP_TEST_DB_HOST');
    if (!$db_host) {
        $local_socket = ini_get('mysqli.default_socket');
        if ($local_socket && file_exists($local_socket)) {
            $db_host = "localhost:{$local_socket}";
            echo "Using MySQL socket: {$local_socket}" . PHP_EOL;
        } else {
            $db_host = 'localhost';
        }
    }
    
    $wp_version = getenv('WP_VERSION') ?: 'latest';
    $skip_db_create = getenv('SKIP_DB_CREATE') ?: false;
    
    // Build the command
    $cmd = "bash {$installer_script} {$db_name} {$db_user} {$db_pass} {$db_host} {$wp_version} " . ($skip_db_create ? "true" : "false");
    
    echo "Running: {$cmd}" . PHP_EOL;
    
    // Execute the installer
    $output = [];
    $return_var = 0;
    exec($cmd, $output, $return_var);
    
    // Output the installation results
    echo implode(PHP_EOL, $output) . PHP_EOL;
    
    if ($return_var !== 0) {
        echo "Failed to install WordPress test environment. Please run bin/install-wp-tests.sh manually." . PHP_EOL;
        exit(1);
    }
    
    echo "WordPress test environment installed successfully." . PHP_EOL;
}

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
    
    // Define the table prefix for hospital manager tables
    if (!defined('HM_TABLE_PREFIX')) {
        global $wpdb;
        define('HM_TABLE_PREFIX', $wpdb->prefix . 'hm_');
    }
    
    // Load the plugin file
	require dirname( dirname( __FILE__ ) ) . '/hospital-manager.php';
}

/**
 * Set up the database tables for testing.
 */
function _setup_hospital_manager_tables() {
    global $wpdb;
    
    // Output the database name and prefix for debugging
    echo "Test database name: {$wpdb->dbname}" . PHP_EOL;
    echo "Test table prefix: {$wpdb->prefix}" . PHP_EOL;
    
    // Load the migration file that defines CreateHospitalTables
    $migration_file = dirname( dirname( __FILE__ ) ) . '/database/migrations/create_hospital_tables.php';
    
    if (!file_exists($migration_file)) {
        echo "Error: Migration file not found at {$migration_file}" . PHP_EOL;
        return;
    }
    
    require_once $migration_file;
    
    // Verify if class exists
    if (!class_exists('CreateHospitalTables')) {
        echo "Error: CreateHospitalTables class not found even after including {$migration_file}" . PHP_EOL;
        return;
    }
    
    try {
        // Drop tables first if they exist to ensure a clean testing environment
        CreateHospitalTables::down();
        
        // Create all the tables
        CreateHospitalTables::up();
        
        // Verify tables were created
        $table_name = $wpdb->prefix . 'hm_patients';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name;
        
        if ($table_exists) {
            echo "Success: Hospital Manager test tables created and verified" . PHP_EOL;
        } else {
            echo "Error: Tables were not created successfully. '{$table_name}' does not exist." . PHP_EOL;
            
            // Check if there were any MySQL errors
            if (!empty($wpdb->last_error)) {
                echo "MySQL Error: {$wpdb->last_error}" . PHP_EOL;
            }
        }
    } catch (Exception $e) {
        echo "Exception during table creation: " . $e->getMessage() . PHP_EOL;
        echo "Stack trace: " . $e->getTraceAsString() . PHP_EOL;
    }
}

tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require "{$_tests_dir}/includes/bootstrap.php";

// Set up database tables after WordPress is fully loaded
_setup_hospital_manager_tables();
