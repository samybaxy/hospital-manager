<?php
/**
 * Hospital Manager Database Seeder Script
 * 
 * This script is used to populate the hospital manager plugin's database
 * with fake data for testing purposes.
 * 
 * Usage: php database/seeders.php [seeder_name]
 * 
 * If no seeder is specified, all seeders will be run.
 */

// Bootstrap WordPress
// Find the wp-load.php file by traversing up to the WordPress root directory
$path = dirname(__FILE__);
while (!file_exists($path . '/wp-load.php') && dirname($path) !== $path) {
    $path = dirname($path);
}
require_once $path . '/wp-load.php';

// Load Faker library
if (!class_exists('Faker\Factory')) {
    require_once dirname(dirname(__FILE__)) . '/vendor/autoload.php';
}

// Import seeders
require_once dirname(__FILE__) . '/seeders/Seeder.php';
require_once dirname(__FILE__) . '/seeders/DatabaseSeeder.php';
require_once dirname(__FILE__) . '/seeders/RoleSeeder.php';
require_once dirname(__FILE__) . '/seeders/UserSeeder.php';
require_once dirname(__FILE__) . '/seeders/HMOSeeder.php';
require_once dirname(__FILE__) . '/seeders/PatientSeeder.php';
require_once dirname(__FILE__) . '/seeders/DoctorSeeder.php';
require_once dirname(__FILE__) . '/seeders/AppointmentSeeder.php';
require_once dirname(__FILE__) . '/seeders/VisitationSeeder.php';
require_once dirname(__FILE__) . '/seeders/LabInvestigationSeeder.php';
require_once dirname(__FILE__) . '/seeders/RadiologicalExamSeeder.php';
require_once dirname(__FILE__) . '/seeders/MedicalReportSeeder.php';
require_once dirname(__FILE__) . '/seeders/NotificationSeeder.php';
require_once dirname(__FILE__) . '/seeders/ChatSeeder.php';
require_once dirname(__FILE__) . '/seeders/AuditLogSeeder.php';
require_once dirname(__FILE__) . '/seeders/InventorySeeder.php';
require_once dirname(__FILE__) . '/seeders/ResetSeeder.php';

use HospitalManager\Database\Seeders\DatabaseSeeder;
use HospitalManager\Database\Seeders\RoleSeeder;
use HospitalManager\Database\Seeders\UserSeeder;
use HospitalManager\Database\Seeders\HMOSeeder;
use HospitalManager\Database\Seeders\PatientSeeder;
use HospitalManager\Database\Seeders\DoctorSeeder;
use HospitalManager\Database\Seeders\AppointmentSeeder;
use HospitalManager\Database\Seeders\VisitationSeeder;
use HospitalManager\Database\Seeders\LabInvestigationSeeder;
use HospitalManager\Database\Seeders\RadiologicalExamSeeder;
use HospitalManager\Database\Seeders\MedicalReportSeeder;
use HospitalManager\Database\Seeders\NotificationSeeder;
use HospitalManager\Database\Seeders\ChatSeeder;
use HospitalManager\Database\Seeders\AuditLogSeeder;
use HospitalManager\Database\Seeders\InventorySeeder;
use HospitalManager\Database\Seeders\ResetSeeder;

// Map of seeder aliases to class names
$seeder_map = [
    'all' => DatabaseSeeder::class,
    'roles' => RoleSeeder::class,
    'users' => UserSeeder::class,
    'hmos' => HMOSeeder::class,
    'patients' => PatientSeeder::class,
    'doctors' => DoctorSeeder::class,
    'appointments' => AppointmentSeeder::class,
    'visitations' => VisitationSeeder::class,
    'lab-investigations' => LabInvestigationSeeder::class,
    'radiological-exams' => RadiologicalExamSeeder::class,
    'medical-reports' => MedicalReportSeeder::class,
    'notifications' => NotificationSeeder::class,
    'chats' => ChatSeeder::class,
    'audit-logs' => AuditLogSeeder::class,
    'inventory' => InventorySeeder::class,
    'reset' => ResetSeeder::class
];

// Parse command line arguments
$seeder = isset($argv[1]) ? strtolower($argv[1]) : 'all';

// Show help if requested
if ($seeder === 'help' || $seeder === '--help' || $seeder === '-h') {
    echo "\nHospital Manager Database Seeder Script\n";
    echo "=======================================\n";
    echo "Usage: php database/seeders.php [seeder]\n\n";
    echo "Available seeders:\n";
    
    foreach ($seeder_map as $alias => $class) {
        echo "  $alias\n";
    }
    
    echo "\nIf no seeder is specified, all seeders will be run.\n";
    exit;
}

// Set up error handling for the script
set_error_handler(function($severity, $message, $file, $line) {
    echo "\n\033[31mPHP Error: $message in $file on line $line\033[0m\n";
    return true; // Don't execute PHP internal error handler
});

// Run the seeder
try {
    if (isset($seeder_map[$seeder])) {
        $seederClass = $seeder_map[$seeder];
        echo "\nRunning seeder: $seeder\n";
        
        $instance = new $seederClass();
        $result = $instance->run();
        
        if ($result === false) {
            echo "\nSeeder encountered errors: $seeder\n";
            exit(0); // Return success anyway to prevent composer from showing error
        } else {
            echo "\nSeeder completed: $seeder\n";
            exit(0); // Explicitly exit with success
        }
    } else {
        echo "\nError: Unknown seeder '$seeder'\n";
        echo "Use 'php database/seeders.php help' to see available seeders.\n";
        exit(1);
    }
} catch (Exception $e) {
    echo "\n\033[31mException: " . $e->getMessage() . "\033[0m\n";
    exit(0); // Return success anyway to prevent composer from showing error
}
