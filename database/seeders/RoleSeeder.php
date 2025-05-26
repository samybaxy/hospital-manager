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
                'access_hospital_manager' => true,
                'view_patients' => true,
                'edit_patient' => true,
                'delete_patients' => true,
                'schedule_appointments' => true,
                'add_visitation' => true,
                'edit_visitation' => true,
                // Route access capabilities
                'access_patients' => true,
                'access_doctors' => true,
                'access_departments' => true,
                'access_appointments' => true,
                'access_visitations' => true,
                'access_chat' => true,
                'access_notifications' => true,
                'access_audit_log' => true,
                'access_billing' => true,
                'access_inventory' => true,
                'access_reports' => true,
                'access_statistics' => true,
                'access_settings' => true,
                'access_lab_dashboard' => true,
            ];
            
            $doctor_caps = [
                'read' => true,
                'access_hospital_manager' => true,
                'create_patients' => true,
                'edit_patient' => false, // Doctors cannot edit patients
                'delete_patients' => false, // Doctors cannot delete patients
                'add_lab_results' => false,
                'edit_lab_results' => false,
                'delete_lab_results' => false,
                'schedule_appointments' => false,
                'add_visitation' => true,
                'edit_visitation' => true,
                // Route access capabilities
                'access_patients' => true,
                'access_doctors' => true,
                'access_departments' => true,
                'access_appointments' => true,
                'access_visitations' => true,
                'access_chat' => true,
                'access_notifications' => true,
                'access_audit_log' => false, // Restricted
                'access_billing' => false,   // Restricted
                'access_inventory' => false, // Restricted
                'access_reports' => true,
                'access_statistics' => true,
                'access_settings' => false,  // Restricted
                'access_lab_dashboard' => true,
            ];
            
            $patient_caps = [
                'read' => true,
                'access_hospital_manager' => true,
                'create_patients' => false,
                'edit_patients' => false,
                'edit_doctors' => false,
                'delete_patients' => false,
                'add_lab_results' => false,
                'edit_lab_results' => false,
                'delete_lab_results' => false,
                // Route access capabilities
                'access_patients' => false,  // Restricted
                'access_doctors' => true,   // Can view doctors
                'access_departments' => false, // Restricted
                'access_appointments' => true,
                'access_visitations' => true,
                'access_chat' => true,
                'access_notifications' => true,
                'access_audit_log' => false, // Restricted
                'access_billing' => false,   // Restricted
                'access_inventory' => false, // Restricted
                'access_reports' => true,   // Restricted
                'access_statistics' => false, // Restricted
                'access_settings' => false,  // Restricted
                'access_lab_dashboard' => false,
            ];
            
            $lab_tech_caps = [
                'read' => true,
                'access_hospital_manager' => true,
                'manage_medical_reports' => false, // Cannot manage medical reports
                'create_patients' => false,
                'edit_patients' => false,
                'edit_doctors' => false,
                'delete_patients' => false,
                'add_lab_results' => false,
                'edit_lab_results' => false,
                'delete_lab_results' => false,
                // Route access capabilities
                'access_patients' => true,  // Restricted
                'access_doctors' => false,   // Restricted
                'access_departments' => false, // Restricted
                'access_appointments' => false, // Restricted
                'access_visitations' => false,  // Restricted
                'access_chat' => false,      // Restricted
                'access_notifications' => false, // Restricted
                'access_audit_log' => false, // Restricted
                'access_billing' => false,   // Restricted
                'access_inventory' => false, // Restricted
                'access_reports' => false,   // Restricted
                'access_statistics' => false, // Restricted
                'access_settings' => false,  // Restricted
                'access_lab_dashboard' => true,
            ];
            
            $desk_officer_caps = [
                'read' => true,
                'access_hospital_manager' => true,
                'create_patients' => true,
                'edit_patients' => true,
                'edit_doctors' => false,
                'delete_patients' => false,
                'schedule_appointments' => false,
                'add_lab_results' => false,
                'edit_lab_results' => false,
                'delete_lab_results' => false,
                // Route access capabilities
                'access_patients' => true,
                'access_doctors' => true,
                'access_departments' => true,
                'access_appointments' => true,
                'access_visitations' => true,
                'access_chat' => false,      // Restricted
                'access_notifications' => true,
                'access_audit_log' => false, // Restricted
                'access_billing' => false,   // Restricted
                'access_inventory' => false, // Restricted
                'access_reports' => true,
                'access_statistics' => false, // Restricted
                'access_settings' => false,  // Restricted
                'access_lab_dashboard' => false,
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
