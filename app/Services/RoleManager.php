<?php

namespace HospitalManager\Services;

class RoleManager
{
    private static function add_roles()
    {
        // Update existing roles with more specific capabilities
        add_role('doctor', 'Doctor', [
            'read' => true,
            'view_patients' => true,
            'edit_patient_biodata' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'view_lab_results' => true
        ]);

        add_role('patient', 'Patient', [
            'read' => true,
            'view_own_records' => true
        ]);

        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'manage_lab_results' => true,
            'view_lab_dashboard' => true
        ]);

        // Add new desk officer role
        add_role('desk_officer', 'Desk Officer', [
            'read' => true,
            'create_patients' => true,
            'edit_patients' => true,
            'delete_patients' => true,
            'view_audit_log' => true
        ]);
    }
}