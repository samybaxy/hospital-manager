<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\RoleManager;
use Brain\Monkey\Functions;
use Mockery;

class RoleManagerTest extends TestCase
{
    /**
     * Track roles that were added during tests
     * @var array
     */
    private $added_roles = [];
    
    /**
     * Track capabilities that were granted
     * @var array
     */
    private $granted_capabilities = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset test data
        $this->added_roles = [];
        $this->granted_capabilities = [];
        
        // Mock the WordPress role management functions using WordPress-MVC pattern
        Functions\when('add_role')->alias(function($role, $display_name, $capabilities) {
            $this->added_roles[$role] = [
                'name' => $display_name,
                'capabilities' => $capabilities
            ];
            
            // Return a WordPress role object as expected by WordPress-MVC
            return $this->createMockRole($role, $capabilities);
        });
        
        // Mock the WordPress get_role function
        Functions\when('get_role')->alias(function($role) {
            if (isset($this->added_roles[$role])) {
                return $this->createMockRole(
                    $role, 
                    $this->added_roles[$role]['capabilities']
                );
            }
            return null;
        });
        
        // Mock remove_role function
        Functions\when('remove_role')->alias(function($role) {
            if (isset($this->added_roles[$role])) {
                unset($this->added_roles[$role]);
            }
            return true;
        });
        
        // Mock WordPress add_capability function
        Functions\when('add_capability')->alias(function($role_obj, $capability, $grant = true) {
            if (is_object($role_obj) && isset($role_obj->name)) {
                $role_name = $role_obj->name;
                if (isset($this->added_roles[$role_name])) {
                    $this->added_roles[$role_name]['capabilities'][$capability] = $grant;
                    $role_obj->capabilities[$capability] = $grant;
                    
                    $this->granted_capabilities[] = [
                        'role' => $role_name,
                        'capability' => $capability,
                        'grant' => $grant
                    ];
                }
            }
            return true;
        });
        
        // Mock WordPress capability filtering
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_role_capabilities') {
                // Allow filtering of role capabilities
                return $value;
            }
            return $value;
        });
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
     * Create a mock WordPress role object
     * 
     * @param string $role_name The role name
     * @param array $capabilities The role capabilities
     * @return object Mock role object
     */
    private function createMockRole($role_name, $capabilities)
    {
        $role = Mockery::mock('WP_Role');
        $role->name = $role_name;
        $role->capabilities = $capabilities;
        
        // Add method for adding capabilities
        $role->shouldReceive('add_cap')
            ->andReturnUsing(function($capability, $grant = true) use ($role) {
                $role->capabilities[$capability] = $grant;
                return true;
            });
            
        return $role;
    }

    /**
     * Test initialization of all hospital manager roles
     */
    public function testInitializeRoles()
    {
        // Expected roles that should be created
        $expected_roles = [
            'doctor',
            'patient',
            'lab_tech',
            'receptionist',
            'hospital_admin'
        ];
        
        // Initialize roles
        RoleManager::initializeRoles();
        
        // Verify all expected roles were created
        foreach ($expected_roles as $role) {
            $this->assertArrayHasKey(
                $role, 
                $this->added_roles,
                "The '$role' role was not created. This role is essential for hospital operations and user permissions."
            );
        }
        
        // Verify doctor role has appropriate capabilities
        $doctor_capabilities = $this->added_roles['doctor']['capabilities'];
        $this->assertArrayHasKey('read_patient_records', $doctor_capabilities, "Doctors need 'read_patient_records' capability to view patient information");
        $this->assertArrayHasKey('create_medical_reports', $doctor_capabilities, "Doctors need 'create_medical_reports' capability to document patient care");
        
        // Verify patient role has appropriate capabilities
        $patient_capabilities = $this->added_roles['patient']['capabilities'];
        $this->assertArrayHasKey('view_own_records', $patient_capabilities, "Patients need 'view_own_records' capability to access their medical information");
        
        // Verify lab_tech role has appropriate capabilities
        $lab_tech_capabilities = $this->added_roles['lab_tech']['capabilities'];
        $this->assertArrayHasKey('manage_lab_results', $lab_tech_capabilities, "Lab technicians need 'manage_lab_results' capability to record test results");
        
        // Verify receptionist role has appropriate capabilities
        $receptionist_capabilities = $this->added_roles['receptionist']['capabilities'];
        $this->assertArrayHasKey('schedule_appointments', $receptionist_capabilities, "Receptionists need 'schedule_appointments' capability to manage patient visits");
        
        // Verify hospital_admin role has appropriate capabilities
        $admin_capabilities = $this->added_roles['hospital_admin']['capabilities'];
        $this->assertArrayHasKey('manage_hospital', $admin_capabilities, "Hospital administrators need 'manage_hospital' capability for overall system management");
    }
    
    /**
     * Test adding a single role
     */
    public function testAddRole()
    {
        $role_id = 'test_role';
        $display_name = 'Test Role';
        $capabilities = [
            'read' => true,
            'custom_capability' => true
        ];
        
        // Add the role
        $result = RoleManager::addRole($role_id, $display_name, $capabilities);
        
        // Verify role was added
        $this->assertTrue($result, "addRole should return true on success");
        $this->assertArrayHasKey($role_id, $this->added_roles, "Role '$role_id' was not added correctly");
        $this->assertEquals($display_name, $this->added_roles[$role_id]['name'], "Role display name is incorrect");
        
        // Verify capabilities
        foreach ($capabilities as $cap => $grant) {
            $this->assertArrayHasKey(
                $cap, 
                $this->added_roles[$role_id]['capabilities'],
                "Role is missing the '$cap' capability"
            );
            $this->assertEquals(
                $grant, 
                $this->added_roles[$role_id]['capabilities'][$cap],
                "Capability '$cap' has incorrect grant value"
            );
        }
    }
    
    /**
     * Test adding a role that already exists
     */
    public function testAddExistingRole()
    {
        // First add a role
        $role_id = 'existing_role';
        $initial_capabilities = ['read' => true];
        RoleManager::addRole($role_id, 'Existing Role', $initial_capabilities);
        
        // Try to add it again with different capabilities
        $new_capabilities = [
            'read' => true,
            'additional_cap' => true
        ];
        
        $result = RoleManager::addRole($role_id, 'Updated Role', $new_capabilities);
        
        // Should return false since role already exists
        $this->assertFalse($result, "Adding an existing role should return false");
        
        // Original role should still exist with original capabilities
        $this->assertArrayHasKey($role_id, $this->added_roles, "Original role should still exist");
        $this->assertArrayNotHasKey(
            'additional_cap', 
            $this->added_roles[$role_id]['capabilities'],
            "Existing role's capabilities should not be updated by addRole"
        );
    }
    
    /**
     * Test removing a role
     */
    public function testRemoveRole()
    {
        // First add a role
        $role_id = 'temporary_role';
        RoleManager::addRole($role_id, 'Temporary Role', ['read' => true]);
        
        // Verify role was added
        $this->assertArrayHasKey($role_id, $this->added_roles, "Role was not added correctly before removal test");
        
        // Remove the role
        $result = RoleManager::removeRole($role_id);
        
        // Verify role was removed
        $this->assertTrue($result, "removeRole should return true on success");
        $this->assertArrayNotHasKey($role_id, $this->added_roles, "Role was not correctly removed");
    }
    
    /**
     * Test adding capabilities to an existing role
     */
    public function testAddCapabilities()
    {
        // First add a role with basic capabilities
        $role_id = 'doctor';
        $initial_capabilities = ['read' => true];
        RoleManager::addRole($role_id, 'Doctor', $initial_capabilities);
        
        // Add new capabilities
        $new_capabilities = [
            'prescribe_medication' => true,
            'order_tests' => true
        ];
        
        $result = RoleManager::addCapabilities($role_id, $new_capabilities);
        
        // Verify capabilities were added
        $this->assertTrue($result, "addCapabilities should return true on success");
        
        // Check all capabilities are present
        $role_capabilities = $this->added_roles[$role_id]['capabilities'];
        foreach (array_merge($initial_capabilities, $new_capabilities) as $cap => $grant) {
            $this->assertArrayHasKey(
                $cap, 
                $role_capabilities,
                "Role is missing the '$cap' capability after addCapabilities"
            );
            $this->assertEquals(
                $grant, 
                $role_capabilities[$cap],
                "Capability '$cap' has incorrect grant value after addCapabilities"
            );
        }
    }
    
    /**
     * Test adding capabilities to a non-existent role
     */
    public function testAddCapabilitiesToNonExistentRole()
    {
        $result = RoleManager::addCapabilities('non_existent_role', ['test' => true]);
        
        // Should return false for non-existent role
        $this->assertFalse($result, "Adding capabilities to a non-existent role should return false");
    }
}
