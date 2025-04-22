<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\LabInvestigation;
use WP_REST_Request;
use WP_REST_Server;

class StatsControllerTest extends TestCase
{
    /**
     * @var \WP_REST_Server
     */
    protected $server;

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
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create test users with different roles
        $this->test_users['admin'] = $this->createUserWithRole('administrator');
        $this->test_users['doctor'] = $this->createUserWithRole('doctor');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        
        // Create test data
        $this->createTestData();
    }

    /**
     * Create test data for stats
     */
    protected function createTestData()
    {
        // Create test patients with different HMOs
        for ($i = 0; $i < 5; $i++) {
            $this->createTestPatient([
                'first_name' => "Patient{$i}",
                'last_name' => 'Test',
                'phone_number' => "123456789{$i}",
                'sex' => 'Male',
                'hmo_id' => $i % 3 + 1, // Distribute across 3 HMOs
            ]);
        }
        
        // Create test visitations for today
        for ($i = 0; $i < 3; $i++) {
            Visitation::create([
                'patient_id' => $i + 1,
                'doctor_id' => $this->test_users['doctor'],
                'date' => date('Y-m-d'),
                'time' => '09:00:00',
                'status' => 'completed',
                'notes' => 'Test visitation'
            ]);
        }
        
        // Create test visitations for previous days
        for ($i = 1; $i <= 5; $i++) {
            Visitation::create([
                'patient_id' => $i % 5 + 1,
                'doctor_id' => $this->test_users['doctor'],
                'date' => date('Y-m-d', strtotime("-{$i} days")),
                'time' => '10:00:00',
                'status' => 'completed',
                'notes' => 'Previous test visitation'
            ]);
        }
        
        // Create test lab investigations with different statuses
        $statuses = ['pending', 'completed', 'cancelled'];
        for ($i = 0; $i < 6; $i++) {
            LabInvestigation::create([
                'patient_id' => $i % 5 + 1,
                'doctor_id' => $this->test_users['doctor'],
                'test_type' => 'Blood Test',
                'status' => $statuses[$i % 3],
                'results' => $i >= 3 ? 'Test results' : null,
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} days")),
            ]);
        }
    }

    /**
     * Test getting stats as admin
     */
    public function testGetStatsAsAdmin()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create request to get stats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data structure
        $data = $response->get_data();
        $this->assertArrayHasKey('totalPatients', $data);
        $this->assertArrayHasKey('activeDoctors', $data);
        $this->assertArrayHasKey('todayVisitations', $data);
        $this->assertArrayHasKey('pendingLabTests', $data);
        $this->assertArrayHasKey('visitationsTrend', $data);
        $this->assertArrayHasKey('patientsByHMO', $data);
        $this->assertArrayHasKey('monthlyLabTests', $data);
        
        // Verify specific stats based on our test data
        $this->assertEquals(5, $data['totalPatients']);
        $this->assertEquals(1, $data['activeDoctors']);
        $this->assertEquals(3, $data['todayVisitations']);
        $this->assertEquals(2, $data['pendingLabTests']); // We created 2 with 'pending' status
        
        // Verify visitation trend data exists
        $this->assertIsArray($data['visitationsTrend']);
        $this->assertNotEmpty($data['visitationsTrend']);
        
        // Verify HMO distribution data exists
        $this->assertIsArray($data['patientsByHMO']);
        $this->assertNotEmpty($data['patientsByHMO']);
        
        // Verify monthly lab tests data exists
        $this->assertIsArray($data['monthlyLabTests']);
    }
    
    /**
     * Test getting stats as non-admin user (should be denied)
     */
    public function testGetStatsAsNonAdmin()
    {
        // Test with doctor role
        wp_set_current_user($this->test_users['doctor']);
        
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats");
        $response = $this->server->dispatch($request);
        
        // Expect 403 Forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Test with patient role
        wp_set_current_user($this->test_users['patient']);
        
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats");
        $response = $this->server->dispatch($request);
        
        // Expect 403 Forbidden
        $this->assertEquals(403, $response->get_status());
    }
}
