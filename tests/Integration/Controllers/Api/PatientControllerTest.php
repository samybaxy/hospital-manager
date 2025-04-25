<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use WP_REST_Request;
use WP_REST_Server;

class PatientControllerTest extends TestCase
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
        $this->test_users['receptionist'] = $this->createUserWithRole('receptionist');
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'],
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'date_of_birth' => '1990-01-01',
            'gender' => 'Male',
            'phone' => '1234567890',
            'address' => '123 Test Street',
            'bio_data' => json_encode([
                'blood_group' => 'O+',
                'allergies' => 'None',
                'emergency_contact' => '09087654321'
            ])
        ]);
    }

    /**
     * Test getting all patients
     */
    public function testGetPatients()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create request to get patients
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertNotEmpty($data['data']['patients']);
        
        // Verify the patient data is correct
        $found = false;
        foreach ($data['data']['patients'] as $patient) {
            if ( (int)$patient->id === $this->test_patient->id ) {
                $found = true;
                $this->assertEquals($this->test_users['patient'], $patient->user_id);
                $this->assertEquals('Test', $patient->first_name);
                $this->assertEquals('Patient', $patient->last_name);
                break;
            }
        }
        $this->assertTrue($found, 'Test patient not found in response');
    }

    /**
     * Test getting a single patient
     */
    public function testGetPatient()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get a specific patient
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$this->test_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        // Verify patient data is correct
        $this->assertEquals($this->test_patient->id, $data['data']->id);
        $this->assertEquals('Test', $data['data']->first_name);
        $this->assertEquals('Patient', $data['data']->last_name);
        $this->assertEquals('1990-01-01', $data['data']->date_of_birth);
        $this->assertEquals('Male', $data['data']->gender);
    }

    /**
     * Test creating a new patient
     */
    public function testCreatePatient()
    {
        // Set current user as administrator
        wp_set_current_user($this->test_users['admin']);
        
        // Create a new user for the patient
        $user_id = $this->factory->user->create([
            'user_login' => 'newpatient',
            'user_pass' => 'password',
            'user_email' => 'newpatient@example.com',
            'role' => 'patient'
        ]);
        
        // Prepare patient data
        $patient_data = [
            'user_id' => $user_id,
            'first_name' => 'New',
            'last_name' => 'Patient',
            'date_of_birth' => '1985-05-15',
            'gender' => 'Female',
            'phone' => '9876543210',
            'address' => '456 New Street',
            'bio_data' => json_encode([
                'blood_group' => 'AB-',
                'allergies' => 'Penicillin',
                'emergency_contact' => '7654321098'
            ])
        ];
        
        // Create request to create a new patient
        $request = new WP_REST_Request('POST', "/{$this->namespace}/patients");
        $request->set_body_params($patient_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(201, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        // Verify the patient was created with correct data
        $this->assertEquals($user_id, $data['data']->user_id);
        $this->assertEquals('New', $data['data']->first_name);
        $this->assertEquals('Female', $data['data']->gender);
        
        // Verify bio_data fields
        $bio_data = json_decode($data['data']->bio_data, true);
        $this->assertEquals('AB-', $bio_data['blood_group']);
        
        // Verify the patient exists in database
        $patient_id = $data['data']->id;
        $created_patient = Patient::find($patient_id);
        $this->assertNotNull($created_patient, 'Patient not found in database');
        
        // Check bio_data in database
        $this->assertNotNull($created_patient->bio_data, 'bio_data is null');
        $patient_bio_data = json_decode($created_patient->bio_data, true);
        $this->assertNotNull($patient_bio_data, 'Failed to decode bio_data JSON');
        $this->assertArrayHasKey('allergies', $patient_bio_data, 'allergies key not found in bio_data');
        $this->assertEquals('Penicillin', $patient_bio_data['allergies']);
    }

    /**
     * Test updating a patient
     */
    public function testUpdatePatient()
    {
        // Set current user as Administrator
        wp_set_current_user($this->test_users['admin']);
        
        // Prepare update data
        $update_data = [
            'phone' => '5555555555',
            'address' => 'Updated Address',
            'bio_data' => json_encode([
                'blood_group' => 'O+',
                'allergies' => 'Updated allergies',
                'emergency_contact' => '09087654321'
            ])
        ];
        
        // Create request to update the patient
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/patients/{$this->test_patient->id}");
        $request->set_body_params($update_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        
        // Verify the patient was updated
        $this->assertEquals('5555555555', $data['data']->phone);
        $this->assertEquals('Updated Address', $data['data']->address);
        
        // Verify bio_data was updated correctly
        $bio_data = json_decode($data['data']->bio_data, true);
        $this->assertEquals('Updated allergies', $bio_data['allergies']);
        
        // Verify the update was saved to database
        $updated_patient = Patient::find($this->test_patient->id);
        $this->assertEquals('5555555555', $updated_patient->phone);
        
        // Verify bio_data in database
        $patient_bio_data = json_decode($updated_patient->bio_data, true);
        $this->assertEquals('Updated allergies', $patient_bio_data['allergies']);
        $this->assertEquals('O+', $patient_bio_data['blood_group']);
    }

    /**
     * Test deleting a patient
     */
    public function testDeletePatient()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create a temporary patient to delete
        $temp_patient = $this->createTestPatient([
            'first_name' => 'Delete',
            'last_name' => 'Patient'
        ]);
        
        // Create request to delete the patient
        $request = new WP_REST_Request('DELETE', "/{$this->namespace}/patients/{$temp_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        
        // Verify the patient was deleted from database
        $deleted_patient = Patient::find($temp_patient->id);
        $this->assertNull($deleted_patient);
    }

    /**
     * Test patient permissions
     */
    public function testPatientPermissions()
    {
        // Set current user as patient (who should not see other patients)
        wp_set_current_user($this->test_users['patient']);
        
        // Try to get all patients
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Now set current user as unauthenticated
        wp_set_current_user(0);
        
        // Try to get a specific patient
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$this->test_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be unauthorized
        $this->assertEquals(401, $response->get_status());
    }

    /**
     * Test patient can view their own record
     */
    public function testPatientCanViewOwnRecord()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to get own patient record
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/me");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        
        // Verify the correct patient data is returned
        $this->assertEquals($this->test_patient->id, $data['data']->id);
        $this->assertEquals($this->test_users['patient'], $data['data']->user_id);
    }

    /**
     * Test searching for patients
     */
    public function testSearchPatients()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create additional test patients with specific search terms
        $this->createTestPatient([
            'first_name' => 'SearchFirst',
            'last_name' => 'TestPatient',
            'bio_data' => json_encode([
                'blood_group' => 'O+',
                'allergies' => 'None',
                'emergency_contact' => '09087654321'
            ])
        ]);
        
        $this->createTestPatient([
            'first_name' => 'Another',
            'last_name' => 'SearchLast',
            'bio_data' => json_encode([
                'blood_group' => 'B+',
                'allergies' => 'Penicillin',
                'emergency_contact' => '09087654321'
            ])
        ]);
        
        // Test search by first name
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/search");
        $request->set_param('query', 'SearchFirst');
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
        $this->assertEquals('SearchFirst', $data['data'][0]->first_name);
        
        // Test search by last name
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/search");
        $request->set_param('query', 'SearchLast');
        $response = $this->server->dispatch($request);
        
        // Check response
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertGreaterThanOrEqual(1, count($data['data']));
        $this->assertEquals('SearchLast', $data['data'][0]->last_name);
        
        // Test search with no results
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/search");
        $request->set_param('query', 'NonExistentPatient');
        $request->set_param('query', 'NonExistentPatient');
        $response = $this->server->dispatch($request);
        
        // Check response
        $this->assertEquals(200, $response->get_status());
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals(0, count($data['data']));
    }

    /**
     * Test creating a patient with missing required fields
     */
    public function testCreatePatientWithMissingRequiredFields()
    {
        // Login as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Prepare incomplete patient data (missing required fields)
        $incomplete_data = [
            'first_name' => 'Test',
            // Missing last_name
            // Missing phone
            'sex' => 'M',
        ];
        
        // Create request to create a patient
        $request = new WP_REST_Request('POST', "/{$this->namespace}/patients");
        $request->set_body_params($incomplete_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 400 Bad Request
        $this->assertEquals(400, $response->get_status());
        
        // Verify error message mentions missing fields
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * Test retrieving a non-existent patient
     */
    public function testGetNonExistentPatient()
    {
        // Login as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Use a patient ID that doesn't exist
        $nonexistent_id = 99999;
        
        // Create request to get a non-existent patient
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$nonexistent_id}");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 404 Not Found
        $this->assertEquals(404, $response->get_status());
        
        // Verify error message
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * Test unauthorized access to patient data
     */
    public function testUnauthorizedAccessToPatient()
    {
        // Create a test patient with specific user_id
        $patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'] // Assign to specific user
        ]);
        
        // Create a different patient user
        $different_patient_id = wp_create_user(
            'different_patient', 
            'password', 
            'different_patient@example.com'
        );
        $different_patient = new \WP_User($different_patient_id);
        $different_patient->set_role('patient');
        
        // Verify role was set correctly
        $different_patient = new \WP_User($different_patient_id);
        $this->assertTrue(in_array('patient', $different_patient->roles), 'Patient role not set correctly');
        
        // Set current user to the different patient
        wp_set_current_user($different_patient_id);
        
        // Attempt to access the patient record that doesn't belong to them
        $request = new WP_REST_Request('GET', "/{$this->namespace}/patients/{$patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 403 Forbidden
        $this->assertEquals(
            403, 
            $response->get_status(), 
            'Expected 403 Forbidden, got ' . $response->get_status() . '. Patient should not access other patients records.'
        );
    }
}
