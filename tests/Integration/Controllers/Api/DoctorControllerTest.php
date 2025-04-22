<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Visitation;
use WP_REST_Request;
use WP_REST_Server;

class DoctorControllerTest extends TestCase
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
     * @var string
     */
    protected $test_visitation = [];

    /**
     * @var Doctor
     */
    protected $test_doctor;

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
        $this->test_users['patient1'] = $this->createUserWithRole('patient');
        $this->test_users['patient2'] = $this->createUserWithRole('patient');
        $this->test_users['nurse'] = $this->createUserWithRole('nurse');
        
        // Create test doctor
        $this->test_doctor = $this->createTestDoctor([
            'user_id' => $this->test_users['doctor'],
            'first_name' => 'Test',
            'last_name' => 'Doctor',
            'specialization' => 'Cardiology'
        ]);
        
        // Create test patients assigned to the doctor
        $this->test_patients[0] = $this->createTestPatient([
            'user_id' => $this->test_users['patient1'],
            'first_name' => 'First',
            'last_name' => 'Patient',
            'doctor_id' => $this->test_doctor->id,
            'phone_number' => '08011112222',
            'age' => 45,
            'sex' => 'M'
        ]);
        
        $this->test_patients[1] = $this->createTestPatient([
            'user_id' => $this->test_users['patient2'],
            'first_name' => 'Second',
            'last_name' => 'Patient',
            'doctor_id' => $this->test_doctor->id,
            'phone_number' => '08033334444',
            'age' => 35,
            'sex' => 'F'
        ]);
        
        // Create test visitation
        $this->test_visitation = $this->createTestVisitation($this->test_patients[0]->id, $this->test_doctor->id, [
            'visit_date' => date('Y-m-d'),
            'visit_time' => '10:00:00',
            'complaint' => 'Chest pain',
            'diagnosis' => 'Suspected angina',
            'status' => 'completed'
        ]);
    }

    /**
     * Helper function to create a test visitation
     */
    protected function createTestVisitation($patient_id, $doctor_id, $data = [])
    {
        $default_data = [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'visit_date' => date('Y-m-d'),
            'visit_time' => '09:00:00',
            'complaint' => 'Test complaint',
            'status' => 'scheduled'
        ];
        
        $data = array_merge($default_data, $data);
        $visitation = Visitation::create($data);
        
        return $visitation;
    }

    /**
     * Test getting doctor's patients
     */
    public function testGetDoctorPatients()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get doctor's patients
        $request = new WP_REST_Request('GET', "/{$this->namespace}/doctor/patients");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('patients', $data['data']);
        
        // Verify both test patients are returned
        $patients = $data['data']['patients'];
        $this->assertCount(2, $patients->items);
        
        // Verify patient data is correct
        $patient_ids = array_map(function($patient) {
            return $patient->id;
        }, $patients->items);
        
        $this->assertContains($this->test_patients[0]->id, $patient_ids);
        $this->assertContains($this->test_patients[1]->id, $patient_ids);
    }

    /**
     * Test that only doctors can access their patients list
     */
    public function testOnlyDoctorsCanAccessPatientsList()
    {
        // Set current user as patient (who shouldn't have access)
        wp_set_current_user($this->test_users['patient1']);
        
        // Create request to get doctor's patients
        $request = new WP_REST_Request('GET', "/{$this->namespace}/doctor/patients");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Set current user as nurse (who also shouldn't have access)
        wp_set_current_user($this->test_users['nurse']);
        
        // Create request again
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test getting doctor's visitations
     */
    public function testGetDoctorVisitations()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get doctor's visitations
        $request = new WP_REST_Request('GET', "/{$this->namespace}/doctor/visitations");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('visitations', $data['data']);
        
        // Verify the test visitation is returned
        $visitations = $data['data']['visitations'];
        $this->assertNotEmpty($visitations->items);
        
        // Verify visitation data is correct
        $found = false;
        foreach ($visitations->items as $visitation) {
            if ($visitation->id === $this->test_visitation->id) {
                $found = true;
                $this->assertEquals($this->test_patients[0]->id, $visitation->patient_id);
                $this->assertEquals($this->test_doctor->id, $visitation->doctor_id);
                $this->assertEquals('Chest pain', $visitation->complaint);
                break;
            }
        }
        $this->assertTrue($found, 'Test visitation not found in response');
    }

    /**
     * Test creating a new visitation
     */
    public function testCreateVisitation()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare visitation data
        $visitation_data = [
            'patient_id' => $this->test_patients[1]->id,
            'visit_date' => date('Y-m-d', strtotime('+1 day')),
            'visit_time' => '11:30:00',
            'complaint' => 'Headache and dizziness',
            'diagnosis' => 'Possible migraine',
            'prescription' => 'Painkillers, rest',
            'notes' => 'Patient to return in one week'
        ];
        
        // Create request to create a new visitation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/doctor/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(201, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        // Verify the visitation was created with correct data
        $this->assertEquals($this->test_patients[1]->id, $data['data']->patient_id);
        $this->assertEquals($this->test_doctor->id, $data['data']->doctor_id);
        $this->assertEquals($visitation_data['complaint'], $data['data']->complaint);
        $this->assertEquals($visitation_data['diagnosis'], $data['data']->diagnosis);
        
        // Verify the visitation exists in database
        $visitation_id = $data['data']->id;
        $created_visitation = Visitation::find($visitation_id);
        $this->assertNotNull($created_visitation);
        $this->assertEquals($this->test_doctor->id, $created_visitation->doctor_id);
    }

    /**
     * Test updating patient biodata
     */
    public function testUpdatePatientBiodata()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare biodata update
        $biodata_update = [
            'blood_group' => 'A+',
            'allergies' => 'Penicillin',
            'chronic_conditions' => 'Hypertension',
            'current_medications' => 'Lisinopril 10mg daily'
        ];
        
        // Create request to update patient biodata
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/doctor/patients/{$this->test_patients[0]->id}/biodata");
        $request->set_body_params($biodata_update);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        
        // Verify biodata was updated
        $this->assertEquals($biodata_update['blood_group'], $data['data']->blood_group);
        $this->assertEquals($biodata_update['allergies'], $data['data']->allergies);
        $this->assertEquals($biodata_update['chronic_conditions'], $data['data']->chronic_conditions);
        
        // Verify the update was saved to database
        $updated_patient = Patient::find($this->test_patients[0]->id);
        $this->assertEquals($biodata_update['blood_group'], $updated_patient->blood_group);
        $this->assertEquals($biodata_update['allergies'], $updated_patient->allergies);
    }

    /**
     * Test that doctor can only update biodata for their own patients
     */
    public function testDoctorCanOnlyUpdateBiodataForOwnPatients()
    {
        // Create a new doctor and patient not assigned to our test doctor
        $another_doctor_user_id = $this->createUserWithRole('doctor');
        $another_doctor = $this->createTestDoctor([
            'user_id' => $another_doctor_user_id,
            'first_name' => 'Another',
            'last_name' => 'Doctor'
        ]);
        
        $another_patient = $this->createTestPatient([
            'first_name' => 'Not',
            'last_name' => 'Assigned',
            'doctor_id' => $another_doctor->id
        ]);
        
        // Set current user as our test doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare biodata update
        $biodata_update = [
            'blood_group' => 'B+',
            'allergies' => 'None'
        ];
        
        // Create request to update patient biodata for a patient not assigned to this doctor
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/doctor/patients/{$another_patient->id}/biodata");
        $request->set_body_params($biodata_update);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
    }

    /**
     * Test that non-doctors cannot create visitations
     */
    public function testNonDoctorsCannotCreateVisitations()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient1']);
        
        // Prepare visitation data
        $visitation_data = [
            'patient_id' => $this->test_patients[1]->id,
            'visit_date' => date('Y-m-d'),
            'visit_time' => '14:00:00',
            'complaint' => 'Test complaint'
        ];
        
        // Create request to create a new visitation
        $request = new WP_REST_Request('POST', "/{$this->namespace}/doctor/visitations");
        $request->set_body_params($visitation_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
    }
}
