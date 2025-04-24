<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;

class AppointmentTest extends TestCase
{
    /**
     * @var Patient Test patient
     */
    protected $patient;
    
    /**
     * @var Doctor Test doctor
     */
    protected $doctor;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test patient and doctor to use in appointments
        $this->patient = $this->createTestPatient();
        $this->doctor = $this->createTestDoctor();
    }
    
    /**
     * Test appointment creation
     */
    public function testCreateAppointment()
    {
        $data = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2025-05-15',
            'appointment_time' => '10:30:00',
            'status' => 'scheduled',
            'reason' => 'Annual checkup',
            'notes' => 'Patient requested morning appointment',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $appointment = Appointment::create($data);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($this->patient->id, $appointment->patient_id);
        $this->assertEquals($this->doctor->id, $appointment->doctor_id);
        $this->assertEquals('2025-05-15', $appointment->appointment_date);
        $this->assertEquals('10:30:00', $appointment->appointment_time);
        $this->assertEquals('scheduled', $appointment->status);
        $this->assertEquals('Annual checkup', $appointment->reason);
    }

    /**
     * Test finding an appointment by ID
     */
    public function testFindAppointment()
    {
        // Create a test appointment
        $appointment = $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        // Find the appointment by ID
        $found_appointment = Appointment::find($appointment->id);
        
        $this->assertInstanceOf(Appointment::class, $found_appointment);
        $this->assertEquals($appointment->id, $found_appointment->id);
        $this->assertEquals($appointment->patient_id, $found_appointment->patient_id);
        $this->assertEquals($appointment->doctor_id, $found_appointment->doctor_id);
        $this->assertEquals($appointment->appointment_date, $found_appointment->appointment_date);
    }

    /**
     * Test finding appointments with where condition
     */
    public function testWhereCondition()
    {
        // Create multiple test appointments
        $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'scheduled'
        ]);
        
        $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'completed'
        ]);
        
        // Get appointments with status = scheduled
        $appointments = (new Appointment())->where('status', 'scheduled')->get();
        
        $this->assertNotEmpty($appointments);
        foreach ($appointments as $appointment) {
            $this->assertInstanceOf(Appointment::class, $appointment);
            $this->assertEquals('scheduled', $appointment->status);
        }
    }

    /**
     * Test ordering appointments
     */
    public function testOrderBy()
    {
        // Create appointments with different dates
        $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2025-06-15'
        ]);
        
        $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'appointment_date' => '2025-05-10'
        ]);
        
        // Get appointments ordered by date ascending
        $appointments = (new Appointment())->orderBy('appointment_date', 'ASC')->get();
        
        // Verify order
        $this->assertGreaterThan(1, count($appointments));
        $prev_date = null;
        foreach ($appointments as $appointment) {
            if ($prev_date !== null) {
                $this->assertGreaterThanOrEqual($prev_date, $appointment->appointment_date);
            }
            $prev_date = $appointment->appointment_date;
        }
    }

    /**
     * Test updating an appointment
     */
    public function testUpdateAppointment()
    {
        // Create a test appointment
        $appointment = $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'status' => 'scheduled'
        ]);
        
        // Update the appointment
        $appointment->status = 'rescheduled';
        $appointment->notes = 'Patient requested to reschedule';
        $appointment->save();
        
        // Retrieve the appointment again
        $updated = Appointment::find($appointment->id);
        
        $this->assertEquals('rescheduled', $updated->status);
        $this->assertEquals('Patient requested to reschedule', $updated->notes);
    }

    /**
     * Test deleting an appointment
     */
    public function testDeleteAppointment()
    {
        // Create a test appointment
        $appointment = $this->createTestAppointment([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        $appointment_id = $appointment->id;
        
        // Delete the appointment
        $appointment->delete();
        
        // Try to find the deleted appointment
        $deleted = Appointment::find($appointment_id);
        
        $this->assertNull($deleted);
    }
}
