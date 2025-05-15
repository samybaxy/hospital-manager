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
            'view_patients' => true,
            'edit_patient' => true,
            'schedule_appointments' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'manage_medical_reports' => true,
            // Route access capabilities for doctors
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
            'view_own_records' => true,
            // Route access capabilities for patients
            'access_patients' => false,  // Restricted
            'access_doctors' => false,   // Restricted
            'access_departments' => false, // Restricted
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false, // Restricted
            'access_billing' => false,   // Restricted
            'access_inventory' => false, // Restricted
            'access_reports' => false,   // Restricted
            'access_statistics' => false, // Restricted
            'access_settings' => false,  // Restricted
            'access_lab_dashboard' => false,
        ]);

        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'access_hospital_manager' => true,
            'manage_medical_reports' => true,
            'view_lab_dashboard' => true,
            // Route access capabilities for lab technicians
            'access_patients' => false,  // Restricted
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
        ]);

        add_role('desk_officer', 'Desk Officer', [
            'read' => true,
            'access_hospital_manager' => true,
            'view_patients' => true,
            'create_patients' => true,
            'edit_patients' => false,
            'delete_patients' => false,
            'schedule_appointments' => false,
            'view_audit_log' => false,
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
            'access_lab_dashboard' => false,
        ]);
    }
    
    /**
     * Get route access map based on user role
     * 
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
        
        return [
            'patients' => $role->has_cap('access_patients'),
            'doctors' => $role->has_cap('access_doctors'),
            'departments' => $role->has_cap('access_departments'),
            'appointments' => $role->has_cap('access_appointments'),
            'visitations' => $role->has_cap('access_visitations'),
            'chat' => $role->has_cap('access_chat'),
            'notifications' => $role->has_cap('access_notifications'),
            'audit_log' => $role->has_cap('access_audit_log'),
            'billing' => $role->has_cap('access_billing'),
            'inventory' => $role->has_cap('access_inventory'),
            'reports' => $role->has_cap('access_reports'),
            'statistics' => $role->has_cap('access_statistics'),
            'settings' => $role->has_cap('access_settings'),
            'lab_dashboard' => $role->has_cap('access_lab_dashboard'),
        ];
    }

    /**
     * Setup role capabilities for the hospital manager
     */
    public static function setupRoles()
    {
        $route_capabilities = [
            'access_hospital_manager' => 'Access to hospital manager',
            'access_patients' => 'Access to patients module',
            'access_doctors' => 'Access to doctors module',
            'access_departments' => 'Access to departments module',
            'access_appointments' => 'Access to appointments module',
            'access_visitations' => 'Access to visitations module',
            'access_chat' => 'Access to chat feature',
            'access_notifications' => 'Access to notifications',
            'access_audit_log' => 'Access to audit logs',
            'access_billing' => 'Access to billing module',
            'access_inventory' => 'Access to inventory module',
            'access_reports' => 'Access to reports',
            'access_statistics' => 'Access to statistics',
            'access_settings' => 'Access to settings',
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

        // Administrator has all permissions
        add_role('administrator', 'Administrator', [
            'read' => true,
            'access_hospital_manager' => true,
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

        // Doctor role with specific permissions
        // Doctors can't access: Audit Log, Billing, Inventory, and Settings
        add_role('doctor', 'Doctor', [
            'read' => true,
            'access_hospital_manager' => true,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => true,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);

        // Patient role with specific permissions
        // Patients can't access: Patients, Doctors, Departments, Audit Log, Billing, 
        // Inventory, Reports, Statistics, and Settings
        add_role('patient', 'Patient', [
            'read' => true,
            'access_hospital_manager' => true,
            'access_patients' => false,
            'access_doctors' => false,
            'access_departments' => false,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => true,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => false,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => false,
        ]);

        // Lab Technician role with specific permissions
        // Lab techs can only access: Dashboard and Lab Dashboard
        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'access_hospital_manager' => true,
            'access_patients' => false,
            'access_doctors' => false,
            'access_departments' => false,
            'access_appointments' => false,
            'access_visitations' => false,
            'access_chat' => false,
            'access_notifications' => false,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => false,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => true,
        ]);

        // Desk Officer role with specific permissions
        // Desk officers can't access: Chat, Audit Log, Billing, Inventory, Statistics, and Settings
        add_role('desk_officer', 'Desk Officer', [
            'read' => true,
            'access_hospital_manager' => true,
            'access_patients' => true,
            'access_doctors' => true,
            'access_departments' => true,
            'access_appointments' => true,
            'access_visitations' => true,
            'access_chat' => false,
            'access_notifications' => true,
            'access_audit_log' => false,
            'access_billing' => false,
            'access_inventory' => false,
            'access_reports' => true,
            'access_statistics' => false,
            'access_settings' => false,
            'access_lab_dashboard' => false,
        ]);
    }
}