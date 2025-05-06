<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use WP_REST_Request;
use WP_REST_Server;

class DoctorPatientAccessTest extends TestCase
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
     * @var array
     */
    protected $test_patients = [];

    /**
     * Set up for each test
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
        $this->test_users['nurse'] = $this->createUserWithRole('nurse');
        $this->test_users['lab_tech'] = $this->createUserWithRole('lab_tech');
        $this->test_users['patient1'] = $this->createUserWithRole('patient');
        $this->test_users['patient2'] = $this->createUserWithRole('patient');
        
        // Create test patients with different assigned doctors
        $this->test_patients[0] = $this->createTestPatient([
            'user_id' => $this->test_users['patient1'],
            'first_name' => 'Assigned',
            'last_name' => 'Patient',
        ]);
        
        $this->test_patients[1] = $this->createTestPatient([
            'user_id' => $this->test_users['patient2'],
            'first_name' => 'Unassigned',
            'last_name' => 'Patient'
        ]);
    }

    /**
     * Test that doctors can access their assigned patients
     */
    public function testDoctorCanAccessAssignedPatients()
    {
        // Set current user as a doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Doctor should be able to access their assigned patient
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$this->test_patients[0]->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be allowed
        $this->assertEquals(200, $response->get_status());
        
        // Check that the doctor has full access to patient data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals($this->test_patients[0]->id, $data['data']->id);
        $this->assertEquals('Assigned', $data['data']->first_name);
        $this->assertEquals('Patient', $data['data']->last_name);
    }

    /**
     * Test that patients can only access their own records
     */
    public function testPatientCanOnlyAccessOwnRecord()
    {
        // Set current user as patient1
        wp_set_current_user($this->test_users['patient1']);
        
        // Patient should be able to access their own record
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/own");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be allowed
        $this->assertEquals(200, $response->get_status());
        
        // Data should match the patient's own record
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals($this->test_patients[0]->id, $data['data']->id);
        
        // Patient should NOT be able to access other patient records
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$this->test_patients[1]->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test role-based access to medical data APIs
     */
    public function testMedicalDataRoleBasedAccess()
    {
        // Create test patient with medical records
        $patient = $this->createTestPatient([
            'first_name' => 'Medical',
            'last_name' => 'Records'
        ]);
        
        // Test access as lab technician
        wp_set_current_user($this->test_users['lab_tech']);
        
        // Lab tech should be able to access lab results
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$patient->id}/lab-results");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be allowed
        $this->assertEquals(200, $response->get_status());
        
        // Lab tech should NOT be able to access prescriptions
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$patient->id}/prescriptions");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Test as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Doctor should be able to access both lab results and prescriptions
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$patient->id}/lab-results");
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$patient->id}/prescriptions");
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
    }

    /**
     * Test administrative access controls
     */
    public function testAdministrativeAccessControls()
    {
        // Admin should have complete access to all endpoints
        wp_set_current_user($this->test_users['admin']);
        
        // Test hospital statistics access
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats/hospital");
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        
        // Doctor should have limited statistics access
        wp_set_current_user($this->test_users['doctor']);
        
        // Can access their own stats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats/doctor");
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        
        // Cannot access hospital-wide stats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats/hospital");
        $response = $this->server->dispatch($request);
        $this->assertEquals(403, $response->get_status());
        
        // Patient should have very limited access
        wp_set_current_user($this->test_users['patient1']);
        
        // Cannot access doctor stats
        $request = new WP_REST_Request('GET', "/{$this->namespace}/stats/doctor");
        $response = $this->server->dispatch($request);
        $this->assertEquals(403, $response->get_status());
    }
}
