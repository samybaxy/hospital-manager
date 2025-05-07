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
require_once dirname(dirname(__FILE__)) . '/wp-load.php';

// Load Faker library
if (!class_exists('Faker\Factory')) {
    require_once dirname(__FILE__) . '/vendor/autoload.php';
}

// Import seeders
require_once dirname(__FILE__) . '/database/seeders/Seeder.php';
require_once dirname(__FILE__) . '/database/seeders/DatabaseSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/RoleSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/UserSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/HMOSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/PatientSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/DoctorSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/AppointmentSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/VisitationSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/LabInvestigationSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/MedicalReportSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/NotificationSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/ChatSeeder.php';
require_once dirname(__FILE__) . '/database/seeders/AuditLogSeeder.php';

use HospitalManager\Database\Seeders\DatabaseSeeder;
use HospitalManager\Database\Seeders\RoleSeeder;
use HospitalManager\Database\Seeders\UserSeeder;
use HospitalManager\Database\Seeders\HMOSeeder;
use HospitalManager\Database\Seeders\PatientSeeder;
use HospitalManager\Database\Seeders\DoctorSeeder;
use HospitalManager\Database\Seeders\AppointmentSeeder;
use HospitalManager\Database\Seeders\VisitationSeeder;
use HospitalManager\Database\Seeders\LabInvestigationSeeder;
use HospitalManager\Database\Seeders\MedicalReportSeeder;
use HospitalManager\Database\Seeders\NotificationSeeder;
use HospitalManager\Database\Seeders\ChatSeeder;
use HospitalManager\Database\Seeders\AuditLogSeeder;

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
    'medical-reports' => MedicalReportSeeder::class,
    'notifications' => NotificationSeeder::class,
    'chats' => ChatSeeder::class,
    'audit-logs' => AuditLogSeeder::class
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

// Run the seeder
if (isset($seeder_map[$seeder])) {
    $seederClass = $seeder_map[$seeder];
    echo "\nRunning seeder: $seeder\n";
    
    $instance = new $seederClass();
    $instance->run();
    
    echo "\nSeeder completed: $seeder\n";
} else {
    echo "\nError: Unknown seeder '$seeder'\n";
    echo "Use 'php database/seeders.php help' to see available seeders.\n";
    exit(1);
}
