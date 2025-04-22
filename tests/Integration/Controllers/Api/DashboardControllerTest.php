<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\MedicalReport;
use WP_REST_Request;
use WP_REST_Server;

class DashboardControllerTest extends TestCase
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
     * @var Patient
     */
    protected $test_patient;

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
        $this->test_users['lab_tech'] = $this->createUserWithRole('lab_tech');
        
        // Create test data
        $this->createTestData();
    }

    /**
     * Create test data for dashboard tests
     */
    protected function createTestData()
    {
        // Create a test patient linked to the patient user
        $this->test_patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'],
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone_number' => '1234567890',
            'sex' => 'Male',
            'address' => '123 Test Street',
            'hmo_id' => 1
        ]);
        
        // Create visitation records for the patient
        for ($i = 0; $i < 3; $i++) {
            Visitation::create([
                'patient_id' => $this->test_patient->id,
                'doctor_id' => $this->test_users['doctor'],
                'date' => date('Y-m-d', strtotime("-{$i} days")),
                'time' => '09:00:00',
                'status' => 'completed',
                'notes' => 'Test visitation'
            ]);
        }
        
        // Create pending lab tests for the patient
        LabInvestigation::create([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'test_type' => 'Blood Test',
            'test_name' => 'Complete Blood Count',
            'status' => 'pending',
            'priority' => 'normal',
            'notes' => 'Test investigation',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create upcoming appointments
        Appointment::create([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'appointment_date' => date('Y-m-d', strtotime('+3 days')),
            'appointment_time' => '10:00:00',
            'status' => 'scheduled',
            'reason' => 'Follow-up check',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create today's appointments for doctor
        Appointment::create([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'appointment_date' => date('Y-m-d'),
            'appointment_time' => '14:00:00',
            'status' => 'scheduled',
            'reason' => 'Consultation',
            'created_at' => date('Y-m-d H:i:s')
        ]);
        
        // Create pending medical reports for doctor
        MedicalReport::create([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'diagnosis' => 'Test diagnosis',
            'treatment' => 'Test treatment',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Test getting dashboard data as a patient
     */
    public function testGetDashboardAsPatient()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to get dashboard data
        $request = new WP_REST_Request('GET', "/{$this->namespace}/dashboard");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertArrayHasKey('patient', $data);
        $this->assertArrayHasKey('recent_visitations', $data);
        $this->assertArrayHasKey('pending_lab_tests', $data);
        $this->assertArrayHasKey('upcoming_appointments', $data);
        
        // Verify patient data
        $this->assertEquals($this->test_patient->id, $data['patient']->id);
        $this->assertEquals($this->test_users['patient'], $data['patient']->user_id);
        $this->assertEquals('Test', $data['patient']->first_name);
        $this->assertEquals('Patient', $data['patient']->last_name);
        
        // Verify visitations data
        $this->assertNotEmpty($data['recent_visitations']);
        $this->assertLessThanOrEqual(5, count($data['recent_visitations']));
        
        // Verify lab tests data
        $this->assertNotEmpty($data['pending_lab_tests']);
        
        // Verify appointments data
        $this->assertNotEmpty($data['upcoming_appointments']);
    }
    
    /**
     * Test getting dashboard data as a doctor
     */
    public function testGetDashboardAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get dashboard data
        $request = new WP_REST_Request('GET', "/{$this->namespace}/dashboard");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertArrayHasKey('todays_appointments', $data);
        $this->assertArrayHasKey('pending_reports', $data);
        $this->assertArrayHasKey('total_patients_today', $data);
        
        // Verify today's appointments
        $this->assertNotEmpty($data['todays_appointments']);
        
        // Verify pending reports
        $this->assertNotEmpty($data['pending_reports']);
        
        // Verify total patients count
        $this->assertEquals(count($data['todays_appointments']), $data['total_patients_today']);
    }
    
    /**
     * Test getting dashboard data as a lab technician
     */
    public function testGetDashboardAsLabTech()
    {
        // Set current user as lab technician
        wp_set_current_user($this->test_users['lab_tech']);
        
        // Create request to get dashboard data
        $request = new WP_REST_Request('GET', "/{$this->namespace}/dashboard");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Lab dashboard should have specific data structure
        $data = $response->get_data();
        // Add assertions based on the lab dashboard structure
        // This will depend on what the get_lab_dashboard method returns
    }
    
    /**
     * Test that unauthenticated users cannot access dashboard
     */
    public function testUnauthorizedAccessToDashboard()
    {
        // Set current user as 0 (not logged in)
        wp_set_current_user(0);
        
        // Create request to get dashboard data
        $request = new WP_REST_Request('GET', "/{$this->namespace}/dashboard");
        $response = $this->server->dispatch($request);
        
        // Check response status (should be 401 Unauthorized)
        $this->assertEquals(401, $response->get_status());
    }
    
    /**
     * Test with an invalid role
     */
    public function testInvalidRoleForDashboard()
    {
        // Create a user with a custom role that doesn't have dashboard data
        $custom_user_id = $this->createUserWithRole('subscriber');
        wp_set_current_user($custom_user_id);
        
        // Create request to get dashboard data
        $request = new WP_REST_Request('GET', "/{$this->namespace}/dashboard");
        $response = $this->server->dispatch($request);
        
        // Check response status (should be 403 Forbidden for invalid role)
        $this->assertEquals(403, $response->get_status());
    }
}
