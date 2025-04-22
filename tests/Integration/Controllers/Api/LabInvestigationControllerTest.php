<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use WP_REST_Request;
use WP_REST_Server;

class LabInvestigationControllerTest extends TestCase
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
     * @var LabInvestigation
     */
    protected $test_investigation;

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
        $this->test_users['lab_technician'] = $this->createUserWithRole('lab_technician');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone_number' => '1234567890',
            'sex' => 'Male',
            'address' => '123 Test Street',
            'hmo_id' => 1
        ]);
        
        // Create a test lab investigation
        $this->test_investigation = $this->createTestLabInvestigation([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_users['doctor'],
            'test_type' => 'Blood Test',
            'test_name' => 'Complete Blood Count',
            'status' => 'pending',
            'priority' => 'normal',
            'notes' => 'Test investigation',
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Create a test lab investigation
     *
     * @param array $data Investigation data
     * @return LabInvestigation
     */
    protected function createTestLabInvestigation($data)
    {
        return LabInvestigation::create($data);
    }

    /**
     * Test getting lab investigations as a doctor
     */
    public function testGetLabInvestigationsAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Add capabilities to the role
        $doctor = get_role('doctor');
        $doctor->add_cap('view_patient_records');
        
        // Create request to get lab investigations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/lab-investigations");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the lab investigation data is correct
        $found = false;
        foreach ($data as $investigation) {
            if ($investigation->id === $this->test_investigation->id) {
                $found = true;
                $this->assertEquals($this->test_patient->id, $investigation->patient_id);
                $this->assertEquals($this->test_users['doctor'], $investigation->doctor_id);
                $this->assertEquals('Blood Test', $investigation->test_type);
                $this->assertEquals('Complete Blood Count', $investigation->test_name);
                $this->assertEquals('pending', $investigation->status);
                break;
            }
        }
        $this->assertTrue($found, 'Test investigation not found in response');
    }
    
    /**
     * Test filtering lab investigations by patient_id
     */
    public function testFilterLabInvestigationsByPatient()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Add capabilities to the role
        $doctor = get_role('doctor');
        $doctor->add_cap('view_patient_records');
        
        // Create request with patient_id filter
        $request = new WP_REST_Request('GET', "/{$this->namespace}/lab-investigations");
        $request->set_param('patient_id', $this->test_patient->id);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // All investigations should belong to the specified patient
        foreach ($data as $investigation) {
            $this->assertEquals($this->test_patient->id, $investigation->patient_id);
        }
    }
    
    /**
     * Test creating a new lab investigation
     */
    public function testCreateLabInvestigation()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Add capabilities to the role
        $doctor = get_role('doctor');
        $doctor->add_cap('manage_lab_investigations');
        
        // Create request to create a new lab investigation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/lab-investigations");
        $request->set_param('patient_id', $this->test_patient->id);
        $request->set_param('doctor_id', $this->test_users['doctor']);
        $request->set_param('test_type', 'Urine Test');
        $request->set_param('test_name', 'Urine Analysis');
        $request->set_param('status', 'pending');
        $request->set_param('priority', 'high');
        $request->set_param('notes', 'Urgent test needed');
        $response = $this->server->dispatch($request);
        
        // Check response status (201 Created)
        $this->assertEquals(201, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertEquals($this->test_patient->id, $data->patient_id);
        $this->assertEquals($this->test_users['doctor'], $data->doctor_id);
        $this->assertEquals('Urine Test', $data->test_type);
        $this->assertEquals('Urine Analysis', $data->test_name);
        $this->assertEquals('pending', $data->status);
        $this->assertEquals('high', $data->priority);
        $this->assertEquals('Urgent test needed', $data->notes);
        
        // Verify the investigation was saved to the database
        $saved_investigation = LabInvestigation::find($data->id);
        $this->assertNotNull($saved_investigation);
        $this->assertEquals('Urine Analysis', $saved_investigation->test_name);
    }
    
    /**
     * Test updating a lab investigation with results
     */
    public function testUpdateLabInvestigation()
    {
        // Set current user as lab technician
        wp_set_current_user($this->test_users['lab_technician']);
        
        // Add capabilities to the role
        $lab_tech = get_role('lab_technician');
        $lab_tech->add_cap('update_lab_results');
        
        // Create request to update the lab investigation
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/lab-investigations/{$this->test_investigation->id}");
        $request->set_param('status', 'completed');
        $request->set_param('results', 'Normal blood count. All values within range.');
        $request->set_param('completed_at', date('Y-m-d H:i:s'));
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        $this->assertEquals($this->test_investigation->id, $data->id);
        $this->assertEquals('completed', $data->status);
        $this->assertEquals('Normal blood count. All values within range.', $data->results);
        
        // Verify the changes were saved to the database
        $updated_investigation = LabInvestigation::find($this->test_investigation->id);
        $this->assertEquals('completed', $updated_investigation->status);
        $this->assertEquals('Normal blood count. All values within range.', $updated_investigation->results);
    }
    
    /**
     * Test updating a non-existent lab investigation
     */
    public function testUpdateNonExistentLabInvestigation()
    {
        // Set current user as lab technician
        wp_set_current_user($this->test_users['lab_technician']);
        
        // Add capabilities to the role
        $lab_tech = get_role('lab_technician');
        $lab_tech->add_cap('update_lab_results');
        
        // Create request with non-existent ID
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/lab-investigations/99999");
        $request->set_param('status', 'completed');
        $request->set_param('results', 'Test results');
        $response = $this->server->dispatch($request);
        
        // Check response status (404 Not Found)
        $this->assertEquals(404, $response->get_status());
    }
    
    /**
     * Test accessing lab investigations without proper permission
     */
    public function testAccessLabInvestigationsWithoutPermission()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Patients should not have permission to view all lab investigations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/lab-investigations");
        $response = $this->server->dispatch($request);
        
        // Expect 403 Forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Patients should not have permission to create lab investigations
        $request = new WP_REST_Request('POST', "/{$this->namespace}/lab-investigations");
        $request->set_param('patient_id', $this->test_patient->id);
        $request->set_param('test_type', 'Blood Test');
        $response = $this->server->dispatch($request);
        
        // Expect 403 Forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Patients should not have permission to update lab investigations
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/lab-investigations/{$this->test_investigation->id}");
        $request->set_param('status', 'completed');
        $response = $this->server->dispatch($request);
        
        // Expect 403 Forbidden
        $this->assertEquals(403, $response->get_status());
    }
}
