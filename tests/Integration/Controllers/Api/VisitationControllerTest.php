<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use WP_REST_Request;
use WP_REST_Server;

class VisitationControllerTest extends TestCase
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
     * @var Doctor
     */
    protected $test_doctor;

    /**
     * @var Visitation
     */
    protected $test_visitation;

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
        $this->test_users['nurse'] = $this->createUserWithRole('nurse');
        $this->test_users['receptionist'] = $this->createUserWithRole('receptionist');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        
        // Create a test doctor
        $this->test_doctor = $this->createTestDoctor([
            'user_id' => $this->test_users['doctor'],
            'first_name' => 'Test',
            'last_name' => 'Doctor',
            'specialization' => 'General Practice'
        ]);
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'],
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'doctor_id' => $this->test_doctor->id
        ]);
        
        // Create a test visitation
        $this->test_visitation = $this->createTestVisitation([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'diagnosis' => 'Test diagnosis',
            'treatment' => 'Test treatment',
            'notes' => 'Test notes'
        ]);
    }

    /**
     * Helper method to create a test visitation
     */
    protected function createTestVisitation($data)
    {
        return Visitation::create($data);
    }

    /**
     * Test retrieving visitations as a doctor
     */
    public function testGetVisitationsAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get visitations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the visitation data is included in the response
        $found = false;
        foreach ($data as $visitation) {
            if ($visitation->id === $this->test_visitation->id) {
                $found = true;
                $this->assertEquals($this->test_patient->id, $visitation->patient_id);
                $this->assertEquals($this->test_doctor->id, $visitation->doctor_id);
                $this->assertEquals('Test diagnosis', $visitation->diagnosis);
                break;
            }
        }
        $this->assertTrue($found, 'Test visitation not found in response');
    }

    /**
     * Test retrieving visitations as a patient
     */
    public function testGetVisitationsAsPatient()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to get visitations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the patient can only see their own visitations
        foreach ($data as $visitation) {
            $this->assertEquals($this->test_patient->id, $visitation->patient_id, 'Patient can see visitations for other patients');
        }
    }

    /**
     * Test creating a new visitation as a doctor
     */
    public function testCreateVisitationAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare visitation data
        $visitation_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d', strtotime('+1 day')),
            'diagnosis' => 'New test diagnosis',
            'treatment' => 'New test treatment',
            'notes' => 'New test notes',
            'follow_up_date' => date('Y-m-d', strtotime('+14 days'))
        ];
        
        // Create request to create a new visitation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(201, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        // Verify the visitation was created with correct data
        $this->assertEquals($this->test_patient->id, $data->patient_id);
        $this->assertEquals($this->test_doctor->id, $data->doctor_id);
        $this->assertEquals('New test diagnosis', $data->diagnosis);
        $this->assertEquals('New test treatment', $data->treatment);
        
        // Verify the visitation exists in database
        $visitation_id = $data->id;
        $created_visitation = Visitation::find($visitation_id);
        $this->assertNotNull($created_visitation);
        $this->assertEquals($visitation_data['follow_up_date'], $created_visitation->follow_up_date);
    }

    /**
     * Test nurses can create visitations
     */
    public function testCreateVisitationAsNurse()
    {
        // Set current user as nurse
        wp_set_current_user($this->test_users['nurse']);
        
        // Prepare visitation data
        $visitation_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'diagnosis' => 'Nurse recorded diagnosis',
            'treatment' => 'Nurse administered treatment',
            'notes' => 'Notes from nurse',
            'vitals' => [
                'temperature' => 37.2,
                'blood_pressure' => '120/80',
                'pulse' => 72
            ]
        ];
        
        // Create request to create a new visitation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - nurses should be allowed
        $this->assertEquals(201, $response->get_status());
        
        // Verify the visitation exists with correct nurse data
        $data = $response->get_data();
        $this->assertEquals('Nurse recorded diagnosis', $data->diagnosis);
    }

    /**
     * Test patients cannot create visitations
     */
    public function testPatientCannotCreateVisitation()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Prepare visitation data
        $visitation_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'diagnosis' => 'Self diagnosis',
            'treatment' => 'Self treatment',
            'notes' => 'Patient notes'
        ];
        
        // Create request to create a new visitation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test filtering visitations by patient ID
     */
    public function testFilterVisitationsByPatientId()
    {
        // Create another patient and visitation
        $another_patient = $this->createTestPatient([
            'first_name' => 'Another',
            'last_name' => 'Patient',
            'doctor_id' => $this->test_doctor->id
        ]);
        
        $another_visitation = $this->createTestVisitation([
            'patient_id' => $another_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'diagnosis' => 'Another diagnosis',
            'treatment' => 'Another treatment'
        ]);
        
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get visitations with patient filter
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $request->set_param('patient_id', $this->test_patient->id);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check that only visitations for the specified patient are returned
        $data = $response->get_data();
        $this->assertNotEmpty($data);
        
        foreach ($data as $visitation) {
            $this->assertEquals($this->test_patient->id, $visitation->patient_id, 'Filtered visitations should only include specified patient');
        }
    }

    /**
     * Test filtering visitations by date range
     */
    public function testFilterVisitationsByDateRange()
    {
        // Create visitations with different dates
        $past_visitation = $this->createTestVisitation([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d', strtotime('-30 days')),
            'diagnosis' => 'Past diagnosis',
            'treatment' => 'Past treatment'
        ]);
        
        $future_visitation = $this->createTestVisitation([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d', strtotime('+30 days')),
            'diagnosis' => 'Future diagnosis',
            'treatment' => 'Future treatment'
        ]);
        
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get recent visitations only
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $request->set_param('start_date', date('Y-m-d', strtotime('-7 days')));
        $request->set_param('end_date', date('Y-m-d', strtotime('+7 days')));
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check that only visitations in the date range are returned
        $data = $response->get_data();
        
        // Should include the current day visitation but not past or future ones
        $current_day_found = false;
        $past_found = false;
        $future_found = false;
        
        foreach ($data as $visitation) {
            if ($visitation->id === $this->test_visitation->id) {
                $current_day_found = true;
            } else if ($visitation->id === $past_visitation->id) {
                $past_found = true;
            } else if ($visitation->id === $future_visitation->id) {
                $future_found = true;
            }
        }
        
        $this->assertTrue($current_day_found, 'Current day visitation should be in results');
        $this->assertFalse($past_found, 'Past visitation should not be in results');
        $this->assertFalse($future_found, 'Future visitation should not be in results');
    }

    /**
     * Test that receptionist cannot access visitation records
     */
    public function testReceptionistPermissions()
    {
        // Set current user as receptionist
        wp_set_current_user($this->test_users['receptionist']);
        
        // Create request to get visitations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Create request to create a visitation
        $visitation_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'notes' => 'Receptionist notes'
        ];
        
        $request = new WP_REST_Request('POST', "/{$this->namespace}/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test unauthenticated user permissions
     */
    public function testUnauthenticatedUserPermissions()
    {
        // Set current user as unauthenticated
        wp_set_current_user(0);
        
        // Try to get visitations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/visitations");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be unauthorized
        $this->assertEquals(401, $response->get_status());
        
        // Try to create a visitation
        $visitation_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'visit_date' => date('Y-m-d'),
            'notes' => 'Unauthorized notes'
        ];
        
        $request = new WP_REST_Request('POST', "/{$this->namespace}/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Should be unauthorized
        $this->assertEquals(401, $response->get_status());
    }
}
