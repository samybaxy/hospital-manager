<?php

namespace HospitalManager\Database\Seeders;

class DatabaseSeeder
{
    /**
     * List of all seeders to run
     */
    protected $seeders = [
        RoleSeeder::class,
        UserSeeder::class,
        HMOSeeder::class,
        PatientSeeder::class,
        DoctorSeeder::class,
        AppointmentSeeder::class, 
        VisitationSeeder::class,
        LabTestCategorySeeder::class,
        LabTestDefinitionSeeder::class,
        LabInvestigationSeeder::class,
        RadiologicalExamSeeder::class,
        NotificationSeeder::class,
        ChatSeeder::class,
        MedicalReportSeeder::class,
        InventorySeeder::class,
        InventorySuppliersSeeder::class,
        InventoryTransactionsSeeder::class,
        InventoryAlertsSeeder::class,
        InventoryReordersSeeder::class,
        AuditLogSeeder::class,
    ];
    
    /**
     * Run all seeders
     */
    public function run()
    {
        echo "\n\033[33m" . "=====================================" . "\033[0m\n";
        echo "\033[33m" . "Starting Hospital Manager Data Seeding" . "\033[0m\n";
        echo "\033[33m" . "=====================================" . "\033[0m\n\n";

        foreach ($this->seeders as $seederClass) {
            echo "\033[33m" . "Running: " . basename(str_replace('\\', '/', $seederClass)) . "\033[0m\n";
            $seeder = new $seederClass();
            $seeder->run();
            echo "\n";
        }
        
        echo "\033[33m" . "=====================================" . "\033[0m\n";
        echo "\033[33m" . "Seeding Complete!" . "\033[0m\n";
        echo "\033[33m" . "=====================================" . "\033[0m\n\n";
    }
}
