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
                'read' => true,
                'access_hospital_manager' => true,  // Add system access capability
                'view_patients' => true,
                'edit_patient' => true,
                'delete_patients' => true,
                'schedule_appointments' => true,
                'add_visitation' => true,
                'edit_visitation' => true,
                'manage_medical_reports' => true,
            ];
            
            $doctor_caps = [
                'read' => true,
                'access_hospital_manager' => true,  // Add system access capability
                'view_patients' => true,
                'edit_patient' => true,
                'schedule_appointments' => true,
                'add_visitation' => true,
                'edit_visitation' => true,
                'manage_medical_reports' => true,
            ];
            
            $patient_caps = [
                'read' => true,
                'access_hospital_manager' => true,  // Add system access capability
                'view_own_records' => true
            ];
            
            $lab_tech_caps = [
                'read' => true,
                'access_hospital_manager' => true,  // Add system access capability
                'manage_medical_reports' => true,
                'view_lab_dashboard' => true
            ];
            
            $desk_officer_caps = [
                'read' => true,
                'access_hospital_manager' => true,  // Add system access capability
                'view_patients' => true,
                'create_patients' => true,
                'edit_patients' => false,
                'delete_patients' => false,
                'schedule_appointments' => false,
                'view_audit_log' => false,
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
