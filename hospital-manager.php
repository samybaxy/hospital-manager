<?php
/**
 * Plugin Name: Hospital Manager
 * Plugin URI: https://github.com/AniomaHospital/hospital-manager
 * Description: A comprehensive hospital management system for Nigerian hospitals with patient, doctor, and operations management.
 * Version: 1.0.0
 * Author: Samuel N
 * Author URI: https://github.com/samybaxy
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: hospital-manager
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// If running in a test environment, provide special handling
if (defined('RUNNING_PHPUNIT_TESTS') && RUNNING_PHPUNIT_TESTS) {
    // Load the mock implementation for tests
    require_once __DIR__ . '/tests/Mocks/MockHospitalManager.php';
    
    // Initialize the test version of our plugin
    add_action('plugins_loaded', ['HospitalManager\\Tests\\Mocks\\MockHospitalManager', 'init']);
    return; // Skip the rest of the file
}

// Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

// Cached service results go stale on any write to a hospital table (see BaseService)
\HospitalManager\Services\BaseService::watchWrites();

// Focused intervention to prevent only wp-login.php redirects after failed auth
add_action('init', function() {
    // Check if this is a REST API request to our namespace
    if (defined('REST_REQUEST') && REST_REQUEST) {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($request_uri, '/wp-json/hospital-manager/v1/auth/login') !== false) {
            // Special handling for login endpoint only
            define('DOING_AJAX', true); // This will prevent WordPress from redirecting

            // Only block redirects to wp-login.php, allow other redirects
            add_filter('wp_redirect', function($location, $status) {
                // Only block wp-login.php redirects
                if (strpos($location, 'wp-login.php') !== false) {
                    return false; // Return false to prevent redirect
                }
                
                // Allow all other redirects
                return $location;
            }, 999, 2);
            
            // Focused handling of login failures
            add_action('wp_login_failed', function($username) {
                // Log the failure without redirecting
                error_log('Hospital Manager: Authentication failed (prevented redirect) for user ' . $username);
                // No redirect
            }, 0);
        }
    }
}, 5); // Very early priority

use WPMVC\Bridge;
use WPMVC\Config;
use HospitalManager\Controllers\FrontendController;
use HospitalManager\Helpers\MenuHelper;
use HospitalManager\Services\RoleService;
use HospitalManager\Services\ApiService;
use HospitalManager\Services\WebSocketService;
use HospitalManager\Services\AuthService;
use HospitalManager\Commands\DatabaseSeederCommand;
use HospitalManager\Middleware\ApiLoggingMiddleware;
use HospitalManager\Middleware\ApiErrorMiddleware;

class HospitalManager extends Bridge
{
    /**
     * The plugin file path.
     * @var string
     */
    protected $file;

    /**
     * Constructor.
     * 
     * @param string $file The plugin file path
     */
    public function __construct($file)
    {
        $config_array = [
            'paths' => [
                'base' => plugin_dir_path($file),
                'controllers' => plugin_dir_path($file) . 'app/Controllers/',
                'views' => plugin_dir_path($file) . 'app/Views/',
                'models' => plugin_dir_path($file) . 'app/Models/',
                'log' => plugin_dir_path($file) . 'logs/', // Add log path
            ],
            'namespace' => 'HospitalManager',
            'domain' => 'hospital-manager',
            'version' => '1.0.0',
        ];
        
        // Create a proper Config object
        $config = new Config($config_array);
        
        // Initialize MVC engine
        $this->mvc = new \WPMVC\MVC\Engine(
            $config->get('paths.views'),
            $config->get('paths.controllers'),
            $config->get('namespace')
        );
        
        // Pass Config object to parent constructor
        parent::__construct($config);
        
        // Store file path for later use
        $this->file = $file;
    }

    /**
     * Plugin name.
     * @var string
     */
    protected $plugin_name = 'hospital-manager';

    /**
     * Plugin version.
     * @var string
     */
    protected $plugin_version = '1.0.0';

    /**
     * Deactivation must not touch data: admins deactivate plugins to debug or
     * while updating. Tables, roles and settings are only removed by uninstall.php,
     * and only when the "delete data on uninstall" option is on.
     */
    public function deactivate_plugin()
    {
        flush_rewrite_rules();
    }

    public function activate_plugin()
    {
        try {
            self::install_schema();
            RoleService::initializeRoles();
            MenuHelper::createHospitalManagerMenuItem();
        } catch (\Throwable $e) {
            error_log('Hospital Manager activation failed: ' . $e->getMessage());
            wp_die(
                esc_html('Hospital Manager could not be activated: ' . $e->getMessage()),
                'Hospital Manager',
                ['back_link' => true]
            );
        }

        $missing = \CreateHospitalTables::missingTables();
        if ($missing) {
            wp_die(
                esc_html('Hospital Manager could not create these database tables: ' . implode(', ', $missing)
                    . '. Check that the database user may CREATE and ALTER tables.'),
                'Hospital Manager',
                ['back_link' => true]
            );
        }
    }

    /** Create or upgrade the tables and record the schema version. */
    public static function install_schema()
    {
        $migration_file = plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
        if (!file_exists($migration_file)) {
            throw new \RuntimeException('Migration file missing: database/migrations/create_hospital_tables.php');
        }
        require_once $migration_file;
        \CreateHospitalTables::up();
        update_option('hospital_manager_db_version', \CreateHospitalTables::SCHEMA_VERSION);
    }

    /**
     * Plugin updates don't run the activation hook, so compare the stored schema
     * version on each load and upgrade the tables when the code is newer.
     */
    public static function maybe_upgrade_schema()
    {
        require_once plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
        if (get_option('hospital_manager_db_version') === \CreateHospitalTables::SCHEMA_VERSION) {
            return;
        }
        try {
            self::install_schema();
            RoleService::initializeRoles();
        } catch (\Throwable $e) {
            error_log('Hospital Manager schema upgrade failed: ' . $e->getMessage());
        }
    }

    public function init()
    {
        add_action('plugins_loaded', [self::class, 'maybe_upgrade_schema']);
        add_action('rest_api_init', [$this, 'register_api_routes']);

        // Initialize the FrontendController
        new FrontendController();
        
        // Initialize WebSocket Service
        WebSocketService::init();
        
        // Initialize Authentication Service
        $authService = new AuthService();
        $authService->init();
        
        // Initialize API Service (CORS, etc.)
        ApiService::init();
        
        // Register API middleware
        ApiLoggingMiddleware::register();
        ApiErrorMiddleware::register();
        
        // Register WP-CLI commands
        if (defined('WP_CLI') && WP_CLI) {
            $this->register_cli_commands();
        }
        
        parent::init();
    }
    
    /**
     * Register WP-CLI commands
     */
    public function register_cli_commands()
    {
        $seederCommand = new DatabaseSeederCommand();
        $seederCommand->register();
    }

    public function register_api_routes()
    {
        // Use the ApiService to register all routes
        ApiService::registerRoutes();
    }
}

// Initialize plugin
$hospital_manager = new HospitalManager(__FILE__);

// Register activation and deactivation hooks
register_activation_hook(__FILE__, [$hospital_manager, 'activate_plugin']);
register_deactivation_hook(__FILE__, [$hospital_manager, 'deactivate_plugin']);

return $hospital_manager;
