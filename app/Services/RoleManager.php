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
        // Update existing roles with more specific capabilities
        add_role('administrator', 'Administrator', [
            'read' => true,
            'access_hospital_manager' => true,  // Add system access capability
            'view_patients' => true,
            'edit_patient' => true,
            'delete_patients' => true,
            'schedule_appointments' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'manage_medical_reports' => true
        ]);

        add_role('doctor', 'Doctor', [
            'read' => true,
            'access_hospital_manager' => true,  // Add system access capability
            'view_patients' => true,
            'edit_patient' => true,
            'schedule_appointments' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'manage_medical_reports' => true
        ]);

        add_role('patient', 'Patient', [
            'read' => true,
            'access_hospital_manager' => true,  // Add system access capability
            'view_own_records' => true
        ]);

        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'access_hospital_manager' => true,  // Add system access capability
            'manage_medical_reports' => true,
            'view_lab_dashboard' => true
        ]);

        // Add new desk officer role
        add_role('desk_officer', 'Desk Officer', [
            'read' => true,
            'access_hospital_manager' => true,  // Add system access capability
            'view_patients' => true,
            'create_patients' => true,
            'edit_patients' => false,
            'delete_patients' => false,
            'schedule_appointments' => false,
            'view_audit_log' => false,
        ]);
    }
}