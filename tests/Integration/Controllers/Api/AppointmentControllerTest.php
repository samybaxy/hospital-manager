<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Doctor;
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
            'specialization' => 'Cardiology'
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
    }

    /**
     * Test getting all appointments
     */
    public function testGetAppointments()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get appointments
        $request = new WP_REST_Request('GET', "/{$this->namespace}/appointments");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
        $this->assertNotEmpty($data['data']['appointments']);
        
        // Verify the appointment data is correct
        $found = false;
        foreach ($data['data']['appointments'] as $appointment) {
            if ($appointment->id === $this->test_appointment->id) {
                $found = true;
                $this->assertEquals($this->test_patient->id, $appointment->patient_id);
                $this->assertEquals($this->test_doctor->id, $appointment->doctor_id);
                $this->assertEquals('scheduled', $appointment->status);
                break;
            }
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
        
        // Verify the appointment was updated
        $this->assertEquals('completed', $data['data']->status);
        $this->assertEquals($update_data['notes'], $data['data']->notes);
        
        // Verify the update was saved to database
        $updated_appointment = Appointment::find($this->test_appointment->id);
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
     * Test creating an appointment with invalid time format
     */
    public function testCreateAppointmentInvalidTimeFormat()
    {
        // Login as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Prepare appointment data with invalid time format
        $future_date = date('Y-m-d', strtotime('+1 day'));
        $appointment_data = [
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'appointment_date' => $future_date,
            'appointment_time' => 'not-a-time', // Invalid time format
            'reason' => 'Test appointment',
        ];
        
        // Create request to create an appointment
        $request = new WP_REST_Request('POST', "/{$this->namespace}/appointments");
        $request->set_body_params($appointment_data);
        $response = $this->server->dispatch($request);
        
        // Check response status - should be 400 Bad Request
        $this->assertEquals(400, $response->get_status());
        
        // Verify error data contains validation errors
        $data = $response->get_data();
        $this->assertFalse($data['success']);
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

    /**
     * Test receptionist permissions for appointment management
     */
    public function testReceptionistAppointmentPermissions()
    {
        // Set current user as receptionist
        wp_set_current_user($this->test_users['receptionist']);
        
        // Receptionists should be able to view all appointments
        $request = new WP_REST_Request('GET', "/{$this->namespace}/appointments");
        $response = $this->server->dispatch($request);
        $this->assertEquals(200, $response->get_status());
        
        // Prepare update data
        $update_data = [
            'status' => 'cancelled',
            'notes' => 'Patient called to cancel'
        ];
        
        // Receptionists should be able to update appointments (e.g., to cancel them)
        $request = new WP_REST_Request('PUT', "/{$this->namespace}/appointments/{$this->test_appointment->id}");
        $request->set_body_params($update_data);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Verify the appointment was updated
        $data = $response->get_data();
        $this->assertTrue($data['success']);
        $this->assertEquals('cancelled', $data['data']->status);
    }
}
