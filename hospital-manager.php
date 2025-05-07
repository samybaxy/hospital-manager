<?php
/**
 * Plugin Name: Hospital Manager
 * Plugin URI: https://github.com/yourusername/hospital-manager
 * Description: A comprehensive hospital management system for Nigerian hospitals with patient, doctor, and operations management.
 * Version: 1.0.0
 * Author: Your Name
 * Author URI: https://github.com/yourusername
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

use WPMVC\Bridge;
use WPMVC\Config;
use HospitalManager\Services\RoleManager;
use HospitalManager\Services\EventStreamService;
use HospitalManager\Services\ApiService;
use HospitalManager\Helpers\MenuHelper;

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

    public function deactivate_plugin()
    {
        try {
            // Log that we're starting deactivation
            error_log('Hospital Manager: Starting plugin deactivation');
            
            // Include the migration file
            $migration_file = plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
            error_log('Hospital Manager: Loading migration file: ' . $migration_file);
            
            if (!file_exists($migration_file)) {
                error_log('Hospital Manager: Migration file not found: ' . $migration_file);
                return;
            }
            
            require_once $migration_file;
            error_log('Hospital Manager: Migration file loaded successfully');
            
            // Cleanup tasks when plugin is deactivated
            error_log('Hospital Manager: Running database cleanup');
            CreateHospitalTables::down();
            error_log('Hospital Manager: Database cleanup completed successfully');

            // Remove custom roles
            remove_role('doctor');
            remove_role('patient');
            remove_role('receptionist');
            remove_role('lab_tech');
            remove_role('desk_officer');
            error_log('Hospital Manager: Custom roles removed successfully');
            
            error_log('Hospital Manager: Plugin deactivation completed successfully');
        } catch (\Exception $e) {
            // Log any exceptions that occur during deactivation
            error_log('Hospital Manager Deactivation Error: ' . $e->getMessage());
            error_log('Hospital Manager Deactivation Error Stack Trace: ' . $e->getTraceAsString());
        }
    }

    public function activate_plugin()
    {
        try {
            // Log that we're starting activation
            error_log('Hospital Manager: Starting plugin activation');
            
            // Step 1: Include the migration file
            $migration_file = plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
            error_log('Hospital Manager: Loading migration file: ' . $migration_file);
            
            if (!file_exists($migration_file)) {
                error_log('Hospital Manager: Migration file not found: ' . $migration_file);
                return;
            }
            
            require_once $migration_file;
            error_log('Hospital Manager: Migration file loaded successfully');
            
            // Step 2: Create database tables
            error_log('Hospital Manager: Starting database table creation');
            CreateHospitalTables::up();
            error_log('Hospital Manager: Database tables created successfully');
            
            // Step 3: Initialize roles
            error_log('Hospital Manager: Initializing roles');
            RoleManager::initializeRoles();
            error_log('Hospital Manager: Roles initialized successfully');

            // Register Menu Helper
            MenuHelper::createHospitalManagerMenuItem();
            error_log('Hospital Manager: Menu item created successfully');
            
            error_log('Hospital Manager: Plugin activation completed successfully');
        } catch (\Exception $e) {
            // Log any exceptions that occur during activation
            error_log('Hospital Manager Activation Error: ' . $e->getMessage());
            error_log('Hospital Manager Activation Error Stack Trace: ' . $e->getTraceAsString());
        }
    }

    public function init()
    {
        add_action('rest_api_init', [$this, 'register_api_routes']);
        
        // Initialize SSE endpoints
        EventStreamService::initEndpoints();
        
        parent::init();
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
