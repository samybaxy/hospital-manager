<?php

namespace HospitalManager\Commands;

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

/**
 * HospitalManager Database Seeder CLI commands
 */
class DatabaseSeederCommand
{
    /**
     * Map of seeder aliases to class names
     */
    protected $seederMap = [
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
        'audit-logs' => AuditLogSeeder::class
    ];
    
    /**
     * Register WP-CLI commands
     */
    public function register()
    {
        if (!class_exists('WP_CLI')) {
            return;
        }
        
        \WP_CLI::add_command('hospital-manager seed', [$this, 'seed']);
        \WP_CLI::add_command('hospital-manager seed:list', [$this, 'listSeeders']);
    }
    
    /**
     * Run database seeders
     * 
     * ## OPTIONS
     * 
     * [<seeder>...]
     * : One or more seeders to run. Use 'all' to run all seeders. 
     * Use 'hospital-manager seed:list' to see available seeders.
     * 
     * [--fresh]
     * : Truncate tables before seeding
     * 
     * ## EXAMPLES
     * 
     *     # Run all seeders
     *     wp hospital-manager seed all
     * 
     *     # Run specific seeders
     *     wp hospital-manager seed roles users
     * 
     *     # Run seeders with fresh data (truncate tables first)
     *     wp hospital-manager seed all --fresh
     * 
     * @param array $args Command arguments
     * @param array $assoc_args Command options
     */
    public function seed($args, $assoc_args)
    {
        // Determine which seeders to run
        $seeders_to_run = [];
        
        if (empty($args) || in_array('all', $args)) {
            // Run all seeders
            $seeders_to_run[] = 'all';
        } else {
            // Run specific seeders
            foreach ($args as $arg) {
                if (isset($this->seederMap[$arg])) {
                    $seeders_to_run[] = $arg;
                } else {
                    \WP_CLI::warning("Unknown seeder: $arg");
                }
            }
        }
        
        if (empty($seeders_to_run)) {
            \WP_CLI::error("No valid seeders specified. Use 'wp hospital-manager seed:list' to see available seeders.");
            return;
        }
        
        // Check if we need to truncate tables
        $fresh = isset($assoc_args['fresh']) && $assoc_args['fresh'];
        
        if ($fresh) {
            \WP_CLI::confirm("This will delete all existing data from the Hospital Manager tables. Are you sure?");
            $this->truncateTables();
        }
        
        // Run the seeders
        foreach ($seeders_to_run as $seeder) {
            $seederClass = $this->seederMap[$seeder];
            
            \WP_CLI::line("");
            \WP_CLI::line(WP_CLI::colorize("%Y" . "Running seeder: $seeder" . "%n"));
            
            if ($seeder === 'all') {
                $instance = new $seederClass();
                $instance->run();
            } else {
                $instance = new $seederClass();
                $instance->run();
            }
            
            \WP_CLI::success("Seeder completed: $seeder");
        }
    }
    
    /**
     * List available seeders
     * 
     * ## EXAMPLES
     * 
     *     wp hospital-manager seed:list
     */
    public function listSeeders()
    {
        \WP_CLI::line("");
        \WP_CLI::line(WP_CLI::colorize("%G" . "Available Database Seeders:" . "%n"));
        \WP_CLI::line("");
        
        foreach ($this->seederMap as $alias => $class) {
            $description = $this->getSeederDescription($alias);
            \WP_CLI::line(WP_CLI::colorize("%Y$alias%n: $description"));
        }
        
        \WP_CLI::line("");
        \WP_CLI::line("Usage: wp hospital-manager seed [seeder]");
        \WP_CLI::line("Use 'all' to run all seeders in the proper order");
    }
    
    /**
     * Get description for a seeder
     * 
     * @param string $alias Seeder alias
     * @return string Seeder description
     */
    protected function getSeederDescription($alias)
    {
        $descriptions = [
            'all' => 'Run all seeders in the proper sequence',
            'roles' => 'Create user roles and capabilities',
            'users' => 'Create demo WordPress users',
            'hmos' => 'Create health management organizations',
            'patients' => 'Create patient records',
            'doctors' => 'Create doctor records',
            'appointments' => 'Create appointment records',
            'visitations' => 'Create patient visitation records',
            'lab-investigations' => 'Create laboratory investigation records',
            'radiological-exams' => 'Create radiological examination records',
            'medical-reports' => 'Create medical report records',
            'notifications' => 'Create system notification records',
            'chats' => 'Create chat conversations between doctors and patients',
            'audit-logs' => 'Create audit log records'
        ];
        
        return $descriptions[$alias] ?? 'No description available';
    }
    
    /**
     * Truncate all hospital manager tables
     */
    protected function truncateTables()
    {
        global $wpdb;
        
        \WP_CLI::line(WP_CLI::colorize("%R" . "Truncating Hospital Manager tables..." . "%n"));
        
        // Temporarily disable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 0');
        
        $tables = [
            'hm_audit_logs',
            'hm_chat_messages',
            'hm_chats',
            'hm_notifications',
            'hm_lab_investigations',
            'hm_radiological_exams',
            'hm_medical_reports',
            'hm_appointments',
            'hm_visitations',
            'hm_patients',
            'hm_doctors',
            'hm_hmos'
        ];
        
        foreach ($tables as $table) {
            $table_name = $wpdb->prefix . $table;
            $wpdb->query("TRUNCATE TABLE {$table_name}");
            \WP_CLI::line("Truncated: {$table_name}");
        }
        
        // Re-enable foreign key checks
        $wpdb->query('SET FOREIGN_KEY_CHECKS = 1');
        
        \WP_CLI::success("Tables truncated successfully");
    }
}
