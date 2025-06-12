<?php
/**
 * Fixed Hospital Manager Seeders Runner
 * This script properly handles dependencies between seeders and ensures roles exist
 */

// Enable error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "=== Hospital Manager Seeders Runner ===\n";

// Find and load WordPress
$wp_load_path = __DIR__ . '/../../../../wp-load.php';
if (!file_exists($wp_load_path)) {
    die("Error: WordPress not found at $wp_load_path\n");
}

require_once $wp_load_path;
echo "WordPress loaded successfully\n";

// Load the base Seeder class
require_once __DIR__ . '/seeders/Seeder.php';

// Define seeders in the correct dependency order
$ordered_seeders = [
    'UserSeeder',          // Creates users with roles (must be first)
    'HMOSeeder',           // Creates HMOs
    'PatientSeeder',       // Creates patients (depends on users & HMOs)
    'DoctorSeeder',        // Creates doctors (depends on users)
    'InventorySuppliersSeeder', // Creates suppliers
    'InventorySeeder',     // Creates inventory items
    'InventoryTransactionsSeeder', // Creates transactions
    'InventoryAlertsSeeder', // Creates alerts
    'InventoryReordersSeeder', // Creates reorders
    'AppointmentSeeder',   // Creates appointments
    'VisitationSeeder',    // Creates visitations
    'LabTestCategorySeeder', // Creates lab test categories
    'LabTestDefinitionSeeder', // Creates lab test definitions
    'LabInvestigationSeeder', // Creates lab tests
    'RadiologicalExamSeeder', // Creates radiology exams
    'NotificationSeeder',  // Creates notifications
    'ChatSeeder',          // Creates chats
    'MedicalReportSeeder', // Creates reports
    'AuditLogSeeder'       // Creates audit logs (should be last)
];

// Parse command line arguments
$seeder_arg = isset($argv[1]) ? strtolower($argv[1]) : 'all';
$seeders_to_run = [];

if ($seeder_arg === 'all') {
    $seeders_to_run = $ordered_seeders;
} elseif ($seeder_arg === 'users') {
    $seeders_to_run = ['UserSeeder'];
} elseif ($seeder_arg === 'patients') {
    $seeders_to_run = ['PatientSeeder'];
} elseif ($seeder_arg === 'doctors') {
    $seeders_to_run = ['DoctorSeeder'];
} elseif ($seeder_arg === 'inventory') {
    $seeders_to_run = [
        'InventorySuppliersSeeder',
        'InventorySeeder',
        'InventoryTransactionsSeeder',
        'InventoryAlertsSeeder',
        'InventoryReordersSeeder'
    ];
} else {
    $seeder_name = ucfirst($seeder_arg) . 'Seeder';
    if (in_array($seeder_name, $ordered_seeders)) {
        $seeders_to_run = [$seeder_name];
    } else {
        echo "Invalid seeder: $seeder_arg\n";
        echo "Available options: all, users, patients, doctors, inventory, " . 
             implode(', ', array_map(function($s) { 
                 return strtolower(str_replace('Seeder', '', $s)); 
             }, $ordered_seeders)) . "\n";
        exit(1);
    }
}

// Ensure all required roles exist before running any seeders
ensureRolesExist();

// Run each seeder with proper error handling
$success_count = 0;
$failed_count = 0;

foreach ($seeders_to_run as $seeder) {
    $seeder_file = __DIR__ . '/seeders/' . $seeder . '.php';
    
    if (!file_exists($seeder_file)) {
        echo "ERROR: Seeder file not found: {$seeder_file}\n";
        $failed_count++;
        continue;
    }
    
    echo "\n=== Running: {$seeder} ===\n";
    
    try {
        require_once $seeder_file;
        
        $class_name = 'HospitalManager\\Database\\Seeders\\' . $seeder;
        
        if (!class_exists($class_name)) {
            echo "ERROR: Class {$class_name} not found in {$seeder_file}\n";
            $failed_count++;
            continue;
        }
        
        $instance = new $class_name();
        $instance->run();
        $success_count++;
        
        echo "✓ {$seeder} completed successfully\n";
        
    } catch (Throwable $e) {
        echo "✗ ERROR in {$seeder}: " . $e->getMessage() . "\n";
        echo "  File: " . $e->getFile() . " Line: " . $e->getLine() . "\n";
        $failed_count++;
    }
}

// Summary
echo "\n=== Seeding Complete ===\n";
echo "Successful: {$success_count}\n";
echo "Failed: {$failed_count}\n";

exit($failed_count > 0 ? 1 : 0);

/**
 * Ensure all required roles exist
 */
function ensureRolesExist() {
    echo "Checking required roles...\n";
    
    $roles = [
        'doctor' => 'Doctor',
        'patient' => 'Patient', 
        'lab_tech' => 'Laboratory Technician',
        'developer' => 'Developer',
        'administrator' => 'Administrator'
    ];
    
    foreach ($roles as $role_key => $role_name) {
        if (!get_role($role_key)) {
            add_role($role_key, $role_name, [
                'read' => true,
                'edit_posts' => $role_key !== 'patient', // Only non-patients can edit posts
                'upload_files' => $role_key !== 'patient', // Only non-patients can upload files
            ]);
            echo "✓ Created '{$role_name}' role\n";
        } else {
            echo "✓ '{$role_name}' role already exists\n";
        }
    }
}
