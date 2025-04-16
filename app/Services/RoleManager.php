<?php

namespace HospitalManager\Services;

class RoleManager
{
    /**
     * Initialize roles and capabilities
     */
    public static function init()
    {
        self::add_roles();
        self::add_capabilities();
    }

    /**
     * Add custom roles
     */
    private static function add_roles()
    {
        add_role('patient', 'Patient', [
            'read' => true,
            'view_patient_dashboard' => true,
            'view_own_records' => true,
        ]);

        add_role('doctor', 'Doctor', [
            'read' => true,
            'view_doctor_dashboard' => true,
            'manage_patient_records' => true,
            'add_visitation' => true,
            'edit_visitation' => true,
            'view_patients' => true,
            'order_lab_investigation' => true,
            'order_radiological_exam' => true,
        ]);

        add_role('lab_tech', 'Lab Technician', [
            'read' => true,
            'view_lab_dashboard' => true,
            'manage_lab_investigations' => true,
            'view_patient_records' => true,
            'update_lab_results' => true,
        ]);
    }

    /**
     * Add capabilities to existing roles
     */
    private static function add_capabilities()
    {
        $administrator = get_role('administrator');
        
        // Add all custom capabilities to administrator
        $capabilities = [
            'view_patient_dashboard',
            'view_own_records',
            'view_doctor_dashboard',
            'manage_patient_records',
            'add_visitation',
            'edit_visitation',
            'view_patients',
            'order_lab_investigation',
            'order_radiological_exam',
            'view_lab_dashboard',
            'manage_lab_investigations',
            'view_patient_records',
            'update_lab_results',
            'manage_hospital_settings',
        ];

        foreach ($capabilities as $cap) {
            $administrator->add_cap($cap);
        }
    }

    /**
     * Remove custom roles and capabilities
     */
    public static function remove()
    {
        // Remove roles
        remove_role('patient');
        remove_role('doctor');
        remove_role('lab_tech');

        // Remove capabilities from administrator
        $administrator = get_role('administrator');
        if ($administrator) {
            $capabilities = [
                'view_patient_dashboard',
                'view_own_records',
                'view_doctor_dashboard',
                'manage_patient_records',
                'add_visitation',
                'edit_visitation',
                'view_patients',
                'order_lab_investigation',
                'order_radiological_exam',
                'view_lab_dashboard',
                'manage_lab_investigations',
                'view_patient_records',
                'update_lab_results',
                'manage_hospital_settings',
            ];

            foreach ($capabilities as $cap) {
                $administrator->remove_cap($cap);
            }
        }
    }
}