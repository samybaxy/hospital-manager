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

// Composer autoload
require_once __DIR__ . '/vendor/autoload.php';

use WPMVC\Bridge;
use HospitalManager\Services\RoleManager;
use HospitalManager\Services\EventStreamService;
use HospitalManager\Services\ApiService;

class HospitalManager extends Bridge
{
    /**
     * Constructor.
     * 
     * @param string $file The plugin file path
     */
    public function __construct($file)
    {
        $this->config = [
            'paths' => [
                'base' => plugin_dir_path($file),
                'controllers' => plugin_dir_path($file) . 'app/Controllers/',
                'views' => plugin_dir_path($file) . 'app/Views/',
                'models' => plugin_dir_path($file) . 'app/Models/',
            ]
        ];
        
        $this->mvc = new \WPMVC\MVC\Engine(
            $this->config['paths']['views'],
            $this->config['paths']['controllers'],
            'HospitalManager'
        );
        parent::__construct($file);
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
        // Cleanup tasks when plugin is deactivated
        CreateHospitalTables::down();
    }

    public function activate_plugin()
    {
        require_once plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
        CreateHospitalTables::up();
        RoleManager::initializeRoles();
    }

    public function init()
    {
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
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
return new HospitalManager(__FILE__);
