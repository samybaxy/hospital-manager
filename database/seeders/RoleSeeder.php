<?php

namespace HospitalManager\Database\Seeders;

use HospitalManager\Services\RoleManager;

class RoleSeeder extends Seeder
{
    /**
     * Add capabilities to a WordPress role
     *
     * @param string $role Role name
     * @param array $capabilities Array of capabilities to add
     */
    private function addCapabilitiesToRole($role, $capabilities)
    {
        $role_obj = get_role($role);
        
        if (!$role_obj) {
            $this->log("Role '{$role}' not found.");
            return;
        }
        
        foreach ($capabilities as $cap => $grant) {
            $role_obj->add_cap($cap, $grant);
        }
    }
    
    public function run()
    {
        try {
            // Use the static method directly instead of creating an instance
            // as RoleManager has static methods
            
            // Initialize roles first
            RoleManager::initializeRoles();
    
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
            
            // Add caps to roles - use the RoleManager static methods or WordPress core functions
            $this->addCapabilitiesToRole('administrator', $admin_caps);
            $this->addCapabilitiesToRole('doctor', $doctor_caps);
            $this->addCapabilitiesToRole('patient', $patient_caps);
            $this->addCapabilitiesToRole('lab_tech', $lab_tech_caps);
            $this->addCapabilitiesToRole('desk_officer', $desk_officer_caps);
            
            $this->log("Role capabilities have been added");
            return true;
        } catch (\Exception $e) {
            $this->log("Error during role seeding: " . $e->getMessage());
            return false;
        }
    }
}
