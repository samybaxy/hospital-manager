<?php
/**
 * Hospital Manager Database Seeder Runner
 * 
 * This script provides a simple command-line interface to run the hospital manager
 * database seeders without requiring WP-CLI.
 * 
 * Usage: php run-seeders.php [seeder_name]
 */

// Define the plugin path
define('HOSPITAL_MANAGER_PLUGIN_DIR', __DIR__);

// Bootstrap WordPress
require_once dirname(dirname(dirname(dirname(__DIR__)))) . '/wp-load.php';

// Check if Faker is available
if (!class_exists('Faker\Factory')) {
    echo "\033[31mError: Faker library is not available. Please run 'composer install' first.\033[0m\n";
    exit(1);
}

// Import base seeder class
require_once __DIR__ . '/seeders/Seeder.php';

// Map of available seeders
$available_seeders = [
    'roles' => 'RoleSeeder',
    'users' => 'UserSeeder',
    'hmos' => 'HMOSeeder',
    'patients' => 'PatientSeeder',
    'doctors' => 'DoctorSeeder',
    'appointments' => 'AppointmentSeeder',
    'visitations' => 'VisitationSeeder',
    'lab-investigations' => 'LabInvestigationSeeder',
    'medical-reports' => 'MedicalReportSeeder',
    'notifications' => 'NotificationSeeder',
    'chats' => 'ChatSeeder',
    'audit-logs' => 'AuditLogSeeder',
    'all' => 'DatabaseSeeder'
];

// Display help information
function show_help() {
    global $available_seeders;
    
    echo "\nHospital Manager Database Seeder Runner\n";
    echo "=====================================\n\n";
    echo "Usage: php run-seeders.php [seeder_name]\n\n";
    echo "Available seeders:\n";
    
    foreach ($available_seeders as $key => $seeder) {
        echo "  - $key\n";
    }
    
    echo "\nUse 'all' to run all seeders in the proper sequence.\n";
}

// Get the seeder name from command line arguments
$seeder_name = isset($argv[1]) ? strtolower($argv[1]) : 'help';

// Show help if requested or no arguments provided
if ($seeder_name === 'help' || $seeder_name === '--help' || $seeder_name === '-h') {
    show_help();
    exit;
}

// Check if the requested seeder exists
if (!isset($available_seeders[$seeder_name])) {
    echo "\033[31mError: Unknown seeder '$seeder_name'.\033[0m\n";
    show_help();
    exit(1);
}

// Load the DatabaseSeeder if running all seeders
if ($seeder_name === 'all') {
    require_once __DIR__ . '/seeders/DatabaseSeeder.php';
    foreach ($available_seeders as $name => $class) {
        if ($name !== 'all') {
            require_once __DIR__ . "/seeders/{$class}.php";
        }
    }
    
    $seeder_class = "HospitalManager\\Database\\Seeders\\{$available_seeders[$seeder_name]}";
    $seeder = new $seeder_class();
    $seeder->run();
    exit;
}

// Otherwise, load just the requested seeder
require_once __DIR__ . "/seeders/{$available_seeders[$seeder_name]}.php";
$seeder_class = "HospitalManager\\Database\\Seeders\\{$available_seeders[$seeder_name]}";

// Run the seeder
try {
    $seeder = new $seeder_class();
    
    echo "\n\033[36m" . "=====================================" . "\033[0m\n";
    echo "\033[36m" . "Running {$available_seeders[$seeder_name]}" . "\033[0m\n";
    echo "\033[36m" . "=====================================" . "\033[0m\n\n";
    
    $seeder->run();
    
    echo "\n\033[36m" . "=====================================" . "\033[0m\n";
    echo "\033[36m" . "Seeding Complete!" . "\033[0m\n";
    echo "\033[36m" . "=====================================" . "\033[0m\n\n";
} catch (Exception $e) {
    echo "\033[31mError: " . $e->getMessage() . "\033[0m\n";
    exit(1);
}
