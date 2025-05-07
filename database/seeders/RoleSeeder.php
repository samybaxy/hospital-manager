<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Services\RoleManagerService;

class RoleSeeder extends Seeder
{
    public function run()
    {
        $roleManager = new RoleManagerService();
        
        // Initialize roles first
        $roleManager->initializeRoles();

        $this->log("Hospital roles have been initialized");
        
        // Add capabilities if needed
        $admin_caps = [
            'manage_hospital' => true,
            'view_reports' => true,
            'manage_patients' => true,
            'manage_doctors' => true,
            'manage_appointments' => true,
            'view_audit_logs' => true,
        ];
        
        $doctor_caps = [
            'view_patients' => true,
            'edit_patients' => true,
            'view_lab_results' => true,
            'create_prescriptions' => true,
            'view_appointments' => true,
            'create_medical_reports' => true,
        ];
        
        $patient_caps = [
            'view_own_records' => true,
            'book_appointments' => true,
            'message_doctors' => true,
            'view_own_lab_results' => true,
        ];
        
        $lab_tech_caps = [
            'process_lab_tests' => true,
            'upload_lab_results' => true,
            'view_lab_requests' => true,
        ];
        
        $desk_officer_caps = [
            'register_patients' => true,
            'schedule_appointments' => true,
            'manage_patient_records' => true,
            'view_appointments' => true,
        ];
        
        // Add caps to roles
        $roleManager->addCapabilities('administrator', $admin_caps);
        $roleManager->addCapabilities('doctor', $doctor_caps);
        $roleManager->addCapabilities('patient', $patient_caps);
        $roleManager->addCapabilities('lab_tech', $lab_tech_caps);
        $roleManager->addCapabilities('desk_officer', $desk_officer_caps);
        
        $this->log("Role capabilities have been added");
    }
}
