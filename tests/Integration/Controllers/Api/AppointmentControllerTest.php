<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Doctor;
use HospitalManager\Tests\Helpers\Debugger;
use HospitalManager\Models\Patient;
use WP_REST_Request;
use WP_REST_Server;

class AppointmentControllerTest extends TestCase
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
     * @var Appointment
     */
    protected $test_appointment;

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
        
        // Create a test doctor
        $this->test_doctor = $this->createTestDoctor([
            'user_id' => $this->test_users['doctor'],
            'first_name' => 'Test',
            'last_name' => 'Doctor',
        ]);        
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'user_id' => $this->test_users['patient'],
            'first_name' => 'Test',
            'last_name' => 'Patient'
        ]);
        
        // Create a test appointment
        $this->test_appointment = $this->createTestAppointment(
            $this->test_patient->id,
            $this->test_doctor->id,
            [
                'appointment_date' => date('Y-m-d', strtotime('+1 day')),
                'appointment_time' => '10:00:00',
                'reason' => 'Regular checkup',
                'status' => 'scheduled'
            ]
        );
        
        // Debug appointment creation
        Debugger::log("Creation result for test appointment:", ($this->test_appointment ? "Success" : "Failed"));
        if ($this->test_appointment) {
            Debugger::log("Test appointment ID:", (isset($this->test_appointment->id) ? $this->test_appointment->id : "No ID found"));
        } else {
            Debugger::log("Failed to create test appointment in setUp()");
        }
    }

    /**
     * Test getting all appointments
     */
    public function testGetAppointments()
    {
        // Verify that our test appointment exists and has an ID before proceeding
        if (empty($this->test_appointment)) {
            $this->fail("Test appointment was not created successfully in setUp()");
        }
        
        // Get appointment ID from attributes if direct property access fails
        $appointment_id = isset($this->test_appointment->id) ? $this->test_appointment->id : 
                        (isset($this->test_appointment->attributes['id']) ? $this->test_appointment->attributes['id'] : null);
                        
        if (empty($appointment_id)) {
            // Try to access protected attributes through reflection if needed
            $reflection = new \ReflectionObject($this->test_appointment);
            $attributes = $reflection->getProperty('attributes');
            $attributes->setAccessible(true);
            $attr_values = $attributes->getValue($this->test_appointment);
            $appointment_id = isset($attr_values['id']) ? $attr_values['id'] : null;
            
            if (empty($appointment_id)) {
                $this->fail("Test appointment was created but has no ID. Attributes: " . print_r($attr_values, true));
            }
        }
        
        Debugger::log("Using appointment ID for test:", $appointment_id);
        
        // Double-check that the appointment exists in the database
        $db_appointment = Appointment::find($appointment_id);
        error_log("DB Appointment check: " . ($db_appointment ? "Found in DB" : "NOT found in DB"));
        if ($db_appointment) {
            error_log("DB Appointment: " . print_r($db_appointment, true));
        }
        
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get appointments
        $request = new WP_REST_Request('GET', "/{$this->namespace}/appointments");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        
        // Add debug information to help diagnose issues
        error_log("Test appointment ID: " . $this->test_appointment->id);
        error_log("Response data structure: " . print_r($data, true));
        
        $this->assertTrue($data['success'], 'API response indicates failure');
        $this->assertArrayHasKey('data', $data, 'API response missing data key');
        
        // Check if appointments exist in the response in the expected format
        if (!isset($data['data']['appointments'])) {
            if (isset($data['data']) && is_array($data['data'])) {
                // Maybe appointments are directly in data
                error_log("Appointments might be directly in data array");
                $appointments = $data['data'];
            } else {
                $this->fail('API response does not contain appointments in the expected format. Response: ' . print_r($data, true));
                return;
            }
        } else {
            $appointments = $data['data']['appointments'];
        }
        
        $this->assertNotEmpty($appointments, 'No appointments returned from API');
        
        // Verify the appointment data is correct
        $found = false;
        foreach ($appointments as $appointment) {
            error_log("Checking appointment: " . print_r($appointment, true));
            
            // First check if $appointment is an object or array
            if (is_object($appointment)) {
                $appointment_id = property_exists($appointment, 'id') ? $appointment->id : null;
                if ($appointment_id == $this->test_appointment->id) {
                    $found = true;
                    $this->assertEquals($this->test_patient->id, $appointment->patient_id);
                    $this->assertEquals($this->test_doctor->id, $appointment->doctor_id);
                    $this->assertEquals('scheduled', $appointment->status);
                    break;
                }
            } else if (is_array($appointment)) {
                $appointment_id = isset($appointment['id']) ? $appointment['id'] : null;
                if ($appointment_id == $this->test_appointment->id) {
                    $found = true;
                    $this->assertEquals($this->test_patient->id, $appointment['patient_id']);
                    $this->assertEquals($this->test_doctor->id, $appointment['doctor_id']);
                    $this->assertEquals('scheduled', $appointment['status']);
                    break;
                }
            }
        }
        
        // If not found, output helpful debug information
        if (!$found) {
            error_log("TEST APPOINTMENT NOT FOUND. Test appointment ID: " . $this->test_appointment->id);
            error_log("Test patient ID: " . $this->test_patient->id);
            error_log("Test doctor ID: " . $this->test_doctor->id);
        }
        
        $this->assertTrue($found, 'Test appointment not found in response');
    }

    /**
     * Test creating a new appointment
     */
    public function testCreateAppointment()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Prepare appointment data
        $appointment_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'appointment_date' => date('Y-m-d', strtotime('+2 days')),
            'appointment_time' => '14:30:00',
            'reason' => 'Flu symptoms',
            'notes' => 'Patient has fever and cough'
        ];
        
        // Create request to create a new appointment
        $request = new WP_REST_Request('POST', "/{$this->namespace}/appointments");
        $request->set_body_params($appointment_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(201, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        // Verify the appointment was created with correct data
        $this->assertEquals($this->test_doctor->id, $data['data']->doctor_id);
        $this->assertEquals($appointment_data['appointment_date'], $data['data']->appointment_date);
        $this->assertEquals($appointment_data['appointment_time'], $data['data']->appointment_time);
        $this->assertEquals($appointment_data['reason'], $data['data']->reason);
        $this->assertEquals('scheduled', $data['data']->status); // Default status
        
        // Verify the appointment exists in database
        $appointment_id = $data['data']->id;
        $created_appointment = Appointment::find($appointment_id);
        $this->assertNotNull($created_appointment);
        $this->assertEquals($this->test_patient->id, $created_appointment->patient_id);
    }

    /**
     * Test updating an appointment
     */
    public function testUpdateAppointment()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare update data
        $update_data = [
            'status' => 'completed',
            'notes' => 'Patient was seen and prescribed medication'
        ];
        
        // Create request to update the appointment
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/appointments/{$this->test_appointment->id}");
        $request->set_body_params($update_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        
        // Debug response data if it doesn't have the expected structure
        if (!isset($data['data']) || !is_object($data['data'])) {
            error_log('Unexpected response data structure: ' . print_r($data, true));
            
            // Handle the case where data might be an array instead of an object
            if (is_array($data['data'])) {
                $this->assertEquals('completed', $data['data']['status']);
                $this->assertEquals($update_data['notes'], $data['data']['notes']);
            } else {
                $this->fail('Response data structure is not as expected');
            }
        } else {
            // Verify the appointment was updated
            $this->assertEquals('completed', $data['data']->status);
            $this->assertEquals($update_data['notes'], $data['data']->notes);
        }
        
        // Verify the update was saved to database
        $updated_appointment = Appointment::find($this->test_appointment->id);
        $this->assertNotNull($updated_appointment, 'Updated appointment not found in database');
        $this->assertEquals('completed', $updated_appointment->status);
    }

    /**
     * Test creating an appointment in the past
     */
    public function testCreateAppointmentInPast()
    {
        // Login as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Prepare appointment data with past date
        $past_date = date('Y-m-d', strtotime('-1 day'));
        $appointment_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'appointment_date' => $past_date,
            'appointment_time' => '10:00:00',
            'reason' => 'Test appointment',
        ];
        
        // Create request to create an appointment
        $request = new WP_REST_Request('POST', "/{$this->namespace}/appointments");
        $request->set_body_params($appointment_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 400 Bad Request
        $this->assertEquals(400, $response->get_status());
        
        // Verify error message
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('past', strtolower($data['message']));
    }

    /**
     * Test scheduling conflicting appointments
     */
    public function testScheduleConflictingAppointments()
    {
        // Login as admin
        wp_set_current_user($this->test_users['admin']);
        
        $future_date = date('Y-m-d', strtotime('+1 day'));
        
        // Create the first appointment
        $first_appointment = $this->createTestAppointment(
            $this->test_patient->id, 
            $this->test_doctor->id,
            [
                'appointment_date' => $future_date,
                'appointment_time' => '10:00:00',
            ]
        );
        
        // Try to create a second appointment at the same time
        $conflicting_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'appointment_date' => $future_date,
            'appointment_time' => '10:00:00', // Same time as first appointment
            'reason' => 'Conflicting appointment',
        ];
        
        // Create request to create a conflicting appointment
        $request = new WP_REST_Request('POST', "/{$this->namespace}/appointments");
        $request->set_body_params($conflicting_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 409 Conflict
        $this->assertEquals(409, $response->get_status());
        
        // Verify error message
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
        $this->assertStringContainsString('conflict', strtolower($data['message']));
    }

    /**
     * Test canceling a non-existent appointment
     */
    public function testCancelNonExistentAppointment()
    {
        // Login as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Use an appointment ID that doesn't exist
        $nonexistent_id = 99999;
        
        // Create request to cancel a non-existent appointment
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/appointments/{$nonexistent_id}");
        $request->set_body_params(['status' => 'canceled']);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 404 Not Found
        $this->assertEquals(404, $response->get_status());
        
        // Verify error message
        $data = $response->get_data();
        $this->assertFalse($data['success']);
        $this->assertArrayHasKey('message', $data);
    }

    /**
     * Test appointment availability check
     */
    public function testGetAvailability()
    {
        // Set current user as patient
        wp_set_current_user($this->test_users['patient']);
        
        // Create request to check availability
        $request = new WP_REST_Request('GET', "/{$this->namespace}/appointments/availability");
        $request->set_query_params([
            'doctor_id' => $this->test_doctor->id,
            'date' => date('Y-m-d', strtotime('+1 day'))
        ]);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('available_slots', $data['data']);
        
        // Verify that the 10:00 slot is marked as unavailable (our test appointment)
        $available_slots = $data['data']['available_slots'];
        foreach ($available_slots as $slot) {
            if ($slot['time'] === '10:00:00') {
                $this->assertFalse($slot['available']);
                break;
            }
        }
    }

    /**
     * Test appointment permissions - non-patients cannot create appointments
     */
    public function testAppointmentPermissions()
    {
        // Set current user as doctor (who cannot create appointments)
        wp_set_current_user($this->test_users['doctor']);
        
        // Prepare appointment data
        $appointment_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'appointment_date' => date('Y-m-d', strtotime('+3 days')),
            'appointment_time' => '11:30:00',
            'reason' => 'Follow-up'
        ];
        
        // Create request to create a new appointment
        $request = new WP_REST_Request('POST', "/{$this->namespace}/appointments");
        $request->set_body_params($appointment_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be forbidden
        $this->assertEquals(403, $response->get_status());
        
        // Now set current user as unauthenticated
        wp_set_current_user(0);
        
        // Try to get appointments
        $request = new WP_REST_Request('GET', "/{$this->namespace}/appointments");
        $response = $this->server->dispatch($request);
        
        // Check response status - should be unauthorized
        $this->assertEquals(401, $response->get_status());
    }
}
