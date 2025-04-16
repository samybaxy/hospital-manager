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

class HospitalManager extends Bridge
{
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

    public function activate_plugin()
    {
        require_once plugin_dir_path(__FILE__) . 'database/migrations/create_hospital_tables.php';
        CreateHospitalTables::up();
        RoleManager::init();
    }

    public function deactivate_plugin()
    {
        RoleManager::remove();
    }

    public function init()
    {
        register_activation_hook(__FILE__, [$this, 'activate_plugin']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate_plugin']);
        parent::init();
    }
}

// Initialize plugin
return new HospitalManager(__FILE__);
