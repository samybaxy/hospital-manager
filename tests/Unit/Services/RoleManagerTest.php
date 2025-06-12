<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\RoleManager;
use Mockery;

/**
 * Tests for the RoleManager service which handles WordPress role creation
 * for the hospital management system
 */
class RoleManagerTest extends TestCase
{
    /**
     * Track roles that were added during tests
     * @var array
     */
    private $added_roles = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        global $wp_roles;
        
        // Remove all custom roles before each test to ensure a clean state
        foreach (['admin', 'doctor', 'patient', 'lab_tech', 'developer'] as $role) {
            if (get_role($role)) {
                remove_role($role);
            }
        }
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Test the public initializeRoles method
     * This method should create all necessary roles with appropriate capabilities
     */
    public function testInitializeRoles()
    {
        // Expected roles that should be created
        $expected_roles = [
            'admin', 
            'doctor', 
            'patient', 
            'lab_tech',
            'developer'
        ];
        
        // Initialize roles
        RoleManager::initializeRoles();
        
        // Verify all expected roles were created
        foreach ($expected_roles as $role) {
            $this->assertNotNull(
                get_role($role),
                "The '$role' role was not created"
            );
        }
        
        // Verify doctor role has appropriate capabilities
        $doctor_role = get_role('doctor');
        $this->assertTrue($doctor_role->has_cap('view_patients'));
        $this->assertTrue($doctor_role->has_cap('edit_patient'));
        $this->assertTrue($doctor_role->has_cap('manage_medical_reports'));
        $this->assertTrue($doctor_role->has_cap('schedule_appointments'));
        $this->assertTrue($doctor_role->has_cap('add_visitation'));
        
        // Verify patient role has appropriate capabilities
        $patient_role = get_role('patient');
        $this->assertTrue($patient_role->has_cap('read'));
        $this->assertTrue($patient_role->has_cap('view_own_records'));
        
        // Verify lab_tech role has appropriate capabilities
        $lab_tech_role = get_role('lab_tech');
        $this->assertTrue($lab_tech_role->has_cap('manage_medical_reports'));
        $this->assertTrue($lab_tech_role->has_cap('view_lab_dashboard'));
    }
    
    /**
     * Test the private add_roles method directly using reflection
     * This ensures the implementation properly creates each role with expected capabilities
     */
    public function testPrivateAddRolesCreatesAllRoles()
    {
        // Use reflection to access private method
        $reflection = new \ReflectionClass(RoleManager::class);
        $method = $reflection->getMethod('add_roles');
        $method->setAccessible(true);
        $method->invoke(null);
        
        // Verify admin role was created with correct capabilities
        $admin_role = get_role('admin');
        $this->assertNotNull($admin_role);
        $this->assertTrue($admin_role->has_cap('read'));
        $this->assertTrue($admin_role->has_cap('view_patients'));
        $this->assertTrue($admin_role->has_cap('edit_patient'));
        $this->assertTrue($admin_role->has_cap('delete_patients'));
        $this->assertTrue($admin_role->has_cap('schedule_appointments'));
        $this->assertTrue($admin_role->has_cap('add_visitation'));
        $this->assertTrue($admin_role->has_cap('edit_visitation'));
        $this->assertTrue($admin_role->has_cap('manage_medical_reports'));
        
        // Verify doctor role was created with correct capabilities
        $doctor_role = get_role('doctor');
        $this->assertNotNull($doctor_role);
        $this->assertTrue($doctor_role->has_cap('read'));
        $this->assertTrue($doctor_role->has_cap('view_patients'));
        $this->assertTrue($doctor_role->has_cap('edit_patient'));
        $this->assertTrue($doctor_role->has_cap('schedule_appointments'));
        $this->assertTrue($doctor_role->has_cap('add_visitation'));
        $this->assertTrue($doctor_role->has_cap('edit_visitation'));
        $this->assertTrue($doctor_role->has_cap('manage_medical_reports'));
        
        // Verify patient role was created with correct capabilities
        $patient_role = get_role('patient');
        $this->assertNotNull($patient_role);
        $this->assertTrue($patient_role->has_cap('read'));
        $this->assertTrue($patient_role->has_cap('view_own_records'));
        
        // Verify lab_tech role was created with correct capabilities
        $lab_tech_role = get_role('lab_tech');
        $this->assertNotNull($lab_tech_role);
        $this->assertTrue($lab_tech_role->has_cap('read'));
        $this->assertTrue($lab_tech_role->has_cap('manage_medical_reports'));
        $this->assertTrue($lab_tech_role->has_cap('view_lab_dashboard'));
        
        // Verify developer role was created with correct capabilities
        $developer_role = get_role('developer');
        $this->assertNotNull($developer_role);
        $this->assertTrue($developer_role->has_cap('read'));
        $this->assertTrue($developer_role->has_cap('view_patients'));
        $this->assertTrue($developer_role->has_cap('create_patients'));
        $this->assertTrue($developer_role->has_cap('edit_patients'));
        $this->assertTrue($developer_role->has_cap('schedule_appointments'));
        $this->assertTrue($developer_role->has_cap('view_audit_log'));
    }
    
    /**
     * Test that desk officer role has all necessary capabilities to perform their job duties
     */
    public function testDeskOfficerHasAppropriateCapabilities()
    {
        RoleManager::initializeRoles();
        
        $developer_role = get_role('developer');
        $this->assertNotNull($developer_role);
        
        // Core capabilities needed for developers
        $this->assertTrue($developer_role->has_cap('read'));
        $this->assertTrue($developer_role->has_cap('view_patients'));
        $this->assertTrue($developer_role->has_cap('create_patients'));
        $this->assertTrue($developer_role->has_cap('edit_patients'));
        $this->assertTrue($developer_role->has_cap('schedule_appointments'));
        $this->assertTrue($developer_role->has_cap('view_audit_log'));
        
        // Developers should not have capabilities reserved for medical staff
        $this->assertFalse($developer_role->has_cap('add_visitation'));
        $this->assertFalse($developer_role->has_cap('edit_visitation'));
        $this->assertFalse($developer_role->has_cap('manage_medical_reports'));
    }
    
    /**
     * Test that patient role has appropriately limited capabilities for security
     */
    public function testPatientHasLimitedCapabilities()
    {
        RoleManager::initializeRoles();
        
        $patient_role = get_role('patient');
        $this->assertNotNull($patient_role);
        
        // Patients should be able to view their own records
        $this->assertTrue($patient_role->has_cap('read'));
        $this->assertTrue($patient_role->has_cap('view_own_records'));
        
        // Patients should not have access to other capabilities
        $this->assertFalse($patient_role->has_cap('view_patients'));
        $this->assertFalse($patient_role->has_cap('edit_patient'));
        $this->assertFalse($patient_role->has_cap('create_patients'));
        $this->assertFalse($patient_role->has_cap('delete_patients'));
        $this->assertFalse($patient_role->has_cap('manage_medical_reports'));
    }
    
    /**
     * Test that doctor role includes all necessary medical capabilities
     */
    public function testDoctorHasMedicalCapabilities()
    {
        RoleManager::initializeRoles();
        
        $doctor_role = get_role('doctor');
        $this->assertNotNull($doctor_role);
        
        // Essential capabilities for doctors
        $this->assertTrue($doctor_role->has_cap('read'));
        $this->assertTrue($doctor_role->has_cap('view_patients'));
        $this->assertTrue($doctor_role->has_cap('edit_patient'));
        $this->assertTrue($doctor_role->has_cap('schedule_appointments'));
        $this->assertTrue($doctor_role->has_cap('add_visitation'));
        $this->assertTrue($doctor_role->has_cap('edit_visitation'));
        $this->assertTrue($doctor_role->has_cap('manage_medical_reports'));
        
        // Doctors shouldn't have admin capabilities
        $this->assertFalse($doctor_role->has_cap('delete_patients'));
    }
    
    /**
     * Test that lab technician role has appropriate lab-related capabilities
     */
    public function testLabTechnicianHasLabCapabilities()
    {
        RoleManager::initializeRoles();
        
        $lab_tech_role = get_role('lab_tech');
        $this->assertNotNull($lab_tech_role);
        
        // Lab technicians should have these capabilities
        $this->assertTrue($lab_tech_role->has_cap('read'));
        $this->assertTrue($lab_tech_role->has_cap('manage_medical_reports'));
        $this->assertTrue($lab_tech_role->has_cap('view_lab_dashboard'));
        
        // Lab technicians shouldn't have patient management capabilities
        $this->assertFalse($lab_tech_role->has_cap('view_patients'));
        $this->assertFalse($lab_tech_role->has_cap('edit_patient'));
        $this->assertFalse($lab_tech_role->has_cap('schedule_appointments'));
    }
}
