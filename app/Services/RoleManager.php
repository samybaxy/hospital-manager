<?php

namespace HospitalManager\Services;

class RoleManager
{
    /**
     * Initialize all custom roles for the hospital management system
     */
    public static function initializeRoles()
    {
        self::add_roles();
    }

    private static function add_roles()
    {
        // Define common route access capabilities
        $route_capabilities = [
            'access_patients' => 'Access to patients page',
            'access_doctors' => 'Access to doctors page',
            'access_departments' => 'Access to departments page',
            'access_appointments' => 'Access to appointments page',
            'access_visitations' => 'Access to visitations page',
            'access_chat' => 'Access to chat feature',
            'access_notifications' => 'Access to notifications',
            'access_audit_log' => 'Access to audit logs',
            'access_billing' => 'Access to billing system',
            'access_inventory' => 'Access to inventory management',
            'access_reports' => 'Access to reports',
            'access_statistics' => 'Access to statistics',
            'access_settings' => 'Access to system settings',
            'access_lab_dashboard' => 'Access to laboratory dashboard',
        ];

        // Register all capabilities with WordPress
        foreach ($route_capabilities as $capability => $description) {
            if (!get_role('administrator')->has_cap($capability)) {
                // Only add if the capability doesn't exist already
                add_role('temp_role', 'Temporary Role', [$capability => true]);
                remove_role('temp_role');
            }
        }

        // Update existing roles with more specific capabilities
        add_role('administrator', 'Administrator', [
            'read' => true,
            'access_hospital_manager' => true,
            'view_patients' => true,
            'edit_patient' => true,
            'delete_patients' => true,
            'schedule_appointments' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'manage_medical_reports' => true,
            // Grant all route access capabilities to administrators
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
        ]);

        add_role('doctor', 'Doctor', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => true, // Can create patients
            'edit_patient' => false, // Cannot edit patients
            'delete_patients' => false, // Cannot delete patients
            'edit_doctors' => false, // Cannot edit doctors
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            'schedule_appointments' => false, // Cannot schedule appointments
            'add_visitation' => true,
            'edit_visitation' => true,
            // Route access capabilities for doctors
            'access_own_records' => true,
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
        ]);

        add_role('patient', 'Patient', [
            'read' => true,
            'access_hospital_manager' => true,
            'create_patients' => false,
            'edit_patients' => false,
            'edit_doctors' => false,
            'delete_patients' => false,
            'add_lab_results' => false,
            'edit_lab_results' => false,
            'delete_lab_results' => false,
            // Route access capabilities for patients
            'access_own_records' => true,
            'access_patients' => false,  // Restricted
            'access_doctors' => true, // Can view doctors
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
            'access_lab_dashboard' => true,
        ]);

        add_role('lab_tech', 'Lab Technician', [
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
            // Route access capabilities for lab technicians
            'access_patients' => true,  // Restricted
            'access_doctors' => false,   // Restricted
            'access_departments' => false, // Restricted
            'access_appointments' => false, // Restricted
            'access_visitations' => false,  // Restricted
            'access_chat' => false,      // Restricted
            'access_notifications' => true, // Un-Restricted
            'access_audit_log' => false, // Restricted
            'access_billing' => false,   // Restricted
            'access_inventory' => false, // Restricted
            'access_reports' => false,   // Restricted
            'access_statistics' => false, // Restricted
            'access_settings' => false,  // Restricted
            'access_lab_dashboard' => true,
        ]);

        add_role('desk_officer', 'Desk Officer', [
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
            // Route access capabilities for desk officers
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
            'access_lab_dashboard' => true,
        ]);
    }
    
    /**
     * Get route access map based on user role
     * 
     * @param string $user_role User role name
     * @return array Route access map with route keys and boolean values
     */
    public static function getRouteAccessMap($user_role)
    {
        if (!$user_role) {
            return [];
        }
        
        $role = get_role($user_role);
        
        if (!$role) {
            return [];
        }
        
        // Get all capabilities for this role
        $capabilities = $role->capabilities;
        
        // Map route access capabilities to route names
        $route_access_map = [
            'patients' => isset($capabilities['access_patients']) ? $capabilities['access_patients'] : false,
            'doctors' => isset($capabilities['access_doctors']) ? $capabilities['access_doctors'] : false,
            'departments' => isset($capabilities['access_departments']) ? $capabilities['access_departments'] : false,
            'appointments' => isset($capabilities['access_appointments']) ? $capabilities['access_appointments'] : false,
            'visitations' => isset($capabilities['access_visitations']) ? $capabilities['access_visitations'] : false,
            'chat' => isset($capabilities['access_chat']) ? $capabilities['access_chat'] : false,
            'notifications' => isset($capabilities['access_notifications']) ? $capabilities['access_notifications'] : false,
            'audit_log' => isset($capabilities['access_audit_log']) ? $capabilities['access_audit_log'] : false,
            'billing' => isset($capabilities['access_billing']) ? $capabilities['access_billing'] : false,
            'inventory' => isset($capabilities['access_inventory']) ? $capabilities['access_inventory'] : false,
            'reports' => isset($capabilities['access_reports']) ? $capabilities['access_reports'] : false,
            'statistics' => isset($capabilities['access_statistics']) ? $capabilities['access_statistics'] : false,
            'settings' => isset($capabilities['access_settings']) ? $capabilities['access_settings'] : false,
            'lab_dashboard' => isset($capabilities['access_lab_dashboard']) ? $capabilities['access_lab_dashboard'] : false,
        ];
        
        return $route_access_map;
    }
}