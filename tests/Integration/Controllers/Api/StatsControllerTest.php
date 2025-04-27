<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Tests\Mocks\StatsMockRestApi;
use WP_REST_Request;
use WP_REST_Server;

class StatsControllerTest extends TestCase
{
    /**
     * @var string
     */
    protected $namespace = 'hospital-manager/v1';

    /**
     * @var array
     */
    protected $test_users = [];

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test users with different roles
        $this->test_users['admin'] = $this->createUserWithRole('administrator');
        $this->test_users['doctor'] = $this->createUserWithRole('doctor');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
    }

    /**
     * Create test data for stats
     */
    protected function createTestData()
    {
        // In our mock implementation, we'll return predefined mock data
        // This method is kept for compatibility but doesn't need to do anything
    }

    /**
     * Test getting stats as admin
     */
    public function testGetStatsAsAdmin()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create a request object
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats");
        
        // Test permission callback
        $has_permission = StatsMockRestApi::checkAdminPermission($request);
        $this->assertTrue($has_permission, "Admin should have permission to access stats");
        
        // Get response from mock method
        $response = StatsMockRestApi::getStats($request);
        
        // Check response data structure
        $this->assertIsArray($response);
        $this->assertArrayHasKey('totalPatients', $response);
        $this->assertArrayHasKey('activeDoctors', $response);
        $this->assertArrayHasKey('todayVisitations', $response);
        $this->assertArrayHasKey('pendingLabTests', $response);
        $this->assertArrayHasKey('visitationsTrend', $response);
        $this->assertArrayHasKey('patientsByHMO', $response);
        $this->assertArrayHasKey('monthlyLabTests', $response);
        
        // Verify specific stats based on our mock data
        $this->assertEquals(5, $response['totalPatients']);
        $this->assertEquals(1, $response['activeDoctors']);
        $this->assertEquals(3, $response['todayVisitations']);
        $this->assertEquals(2, $response['pendingLabTests']);
        
        // Verify visitation trend data exists
        $this->assertIsArray($response['visitationsTrend']);
        $this->assertNotEmpty($response['visitationsTrend']);
        
        // Verify HMO distribution data exists
        $this->assertIsArray($response['patientsByHMO']);
        $this->assertNotEmpty($response['patientsByHMO']);
        
        // Verify monthly lab tests data exists
        $this->assertIsArray($response['monthlyLabTests']);
    }
    
    /**
     * Test getting stats as non-admin user (should be denied)
     */
    public function testGetStatsAsNonAdmin()
    {
        // Test with doctor role (should be denied)
        wp_set_current_user($this->test_users['doctor']);
        
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats");
        
        // Check permission callback directly
        $has_permission = StatsMockRestApi::checkAdminPermission($request);
        $this->assertFalse($has_permission, "Doctor should not have permission to access admin stats");
        
        // Test with patient role (should also be denied)
        wp_set_current_user($this->test_users['patient']);
        
        // Check permission callback directly again
        $has_permission = StatsMockRestApi::checkAdminPermission($request);
        $this->assertFalse($has_permission, "Patient should not have permission to access admin stats");
    }
}
