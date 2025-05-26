<?php

namespace HospitalManager\Tests\Integration;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\RoleManager;

class RoleBasedAccessTest extends TestCase
{
    /**
     * Test user role creation and capabilities
     */
    public function testRoleInitialization()
    {
        // Re-initialize roles to ensure they are created
        RoleManager::initializeRoles();
        
        // Test that hospital roles exist
        $this->assertTrue(get_role('doctor') !== null);
        $this->assertTrue(get_role('nurse') !== null);
        $this->assertTrue(get_role('receptionist') !== null);
        $this->assertTrue(get_role('lab_tech') !== null);
        $this->assertTrue(get_role('patient') !== null);
        
        // Verify doctor capabilities
        $doctor_role = get_role('doctor');
        $this->assertTrue($doctor_role->has_cap('read'));
        $this->assertTrue($doctor_role->has_cap('view_patients'));
        $this->assertTrue($doctor_role->has_cap('edit_medical_records'));
        $this->assertTrue($doctor_role->has_cap('view_lab_results'));
        
        // Verify nurse capabilities
        $nurse_role = get_role('nurse');
        $this->assertTrue($nurse_role->has_cap('read'));
        $this->assertTrue($nurse_role->has_cap('view_patients'));
        $this->assertTrue($nurse_role->has_cap('update_vitals'));
        
        // Verify patient capabilities (limited)
        $patient_role = get_role('patient');
        $this->assertTrue($patient_role->has_cap('read'));
        $this->assertTrue($patient_role->has_cap('view_own_records'));
        $this->assertFalse($patient_role->has_cap('view_patients'));
        $this->assertFalse($patient_role->has_cap('edit_medical_records'));
    }
    
    /**
     * Test access control for various user roles
     */
    public function testUserRoleAccess()
    {
        // Create users with different roles
        $admin_id = $this->createUserWithRole('administrator');
        $doctor_id = $this->createUserWithRole('doctor');
        $nurse_id = $this->createUserWithRole('nurse');
        $receptionist_id = $this->createUserWithRole('receptionist');
        $patient_id = $this->createUserWithRole('patient');
        
        // Test admin permissions
        wp_set_current_user($admin_id);
        $this->assertTrue(current_user_can('manage_options'));
        $this->assertTrue(current_user_can('view_patients'));
        $this->assertTrue(current_user_can('edit_medical_records'));
        $this->assertTrue(current_user_can('manage_hospital'));
        
        // Test doctor permissions
        wp_set_current_user($doctor_id);
        $this->assertTrue(current_user_can('view_patients'));
        $this->assertTrue(current_user_can('edit_medical_records'));
        $this->assertTrue(current_user_can('create_prescriptions'));
        $this->assertFalse(current_user_can('manage_options'));
        $this->assertFalse(current_user_can('manage_hospital'));
        
        // Test receptionist permissions
        wp_set_current_user($receptionist_id);
        $this->assertTrue(current_user_can('view_patients'));
        $this->assertTrue(current_user_can('manage_appointments'));
        $this->assertTrue(current_user_can('register_patients'));
        $this->assertFalse(current_user_can('edit_medical_records'));
        $this->assertFalse(current_user_can('create_prescriptions'));
        
        // Test patient permissions
        wp_set_current_user($patient_id);
        $this->assertTrue(current_user_can('view_own_records'));
        $this->assertTrue(current_user_can('view_own_appointments'));
        $this->assertFalse(current_user_can('view_patients'));
        $this->assertFalse(current_user_can('edit_medical_records'));
    }
    
    /**
     * Test access control for sensitive operations
     */
    public function testSensitiveOperationsAccess()
    {
        // Create test patient
        $patient = $this->createTestPatient();
        
        // Create users with different roles
        $admin_id = $this->createUserWithRole('administrator');
        $doctor_id = $this->createUserWithRole('doctor');
        $receptionist_id = $this->createUserWithRole('receptionist');
        $patient_id = $this->createUserWithRole('patient');
        
        // Test deletion permissions - only admin should be able to delete patients
        wp_set_current_user($admin_id);
        $this->assertTrue(current_user_can('delete_patient', $patient->ID));
        
        wp_set_current_user($doctor_id);
        $this->assertFalse(current_user_can('delete_patient', $patient->ID));
        
        wp_set_current_user($receptionist_id);
        $this->assertFalse(current_user_can('delete_patient', $patient->ID));
        
        // Test medical record edit permissions
        wp_set_current_user($admin_id);
        $this->assertTrue(current_user_can('edit_medical_records'));
        
        wp_set_current_user($doctor_id);
        $this->assertTrue(current_user_can('edit_medical_records'));
        
        wp_set_current_user($receptionist_id);
        $this->assertFalse(current_user_can('edit_medical_records'));
        
        wp_set_current_user($patient_id);
        $this->assertFalse(current_user_can('edit_medical_records'));
    }
    
    /**
     * Test patient's access to their own records
     */
    public function testPatientOwnRecordAccess()
    {
        // Create a test patient with user account
        $patient_user_id = $this->createUserWithRole('patient');
        $patient = $this->createTestPatient([
            'user_id' => $patient_user_id,
            'first_name' => 'Patient',
            'last_name' => 'WithUser'
        ]);
        
        // Create another test patient
        $other_patient = $this->createTestPatient([
            'first_name' => 'Other',
            'last_name' => 'Patient'
        ]);
        
        // Set current user as the patient
        wp_set_current_user($patient_user_id);
        
        // Test that the patient can view their own record
        $this->assertTrue(current_user_can('view_patient_record', $patient->ID));
        
        // Test that the patient cannot view other patient records
        $this->assertFalse(current_user_can('view_patient_record', $other_patient->ID));
        
        // Doctor should be able to view all patients
        $doctor_id = $this->createUserWithRole('doctor');
        wp_set_current_user($doctor_id);
        $this->assertTrue(current_user_can('view_patient_record', $patient->ID));
        $this->assertTrue(current_user_can('view_patient_record', $other_patient->ID));
    }
}
