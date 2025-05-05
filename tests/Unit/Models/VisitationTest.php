<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\LabInvestigation;

class VisitationTest extends TestCase
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
        
        // Create a test patient and doctor to use in visitations
        $this->patient = $this->createTestPatient();
        $this->doctor = $this->createTestDoctor();
    }
    
    /**
     * Test visitation creation
     */
    public function testCreateVisitation()
    {
        $data = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'date' => '2025-04-22',
            'time' => '09:15:00',
            'medical_history' => 'Patient reports recurring headaches for the past two weeks',
            'diagnosis' => 'Tension headache, possible migraine',
            'treatment' => 'Prescribed Paracetamol 500mg twice daily for one week'
        ];

        $visitation = Visitation::create($data);

        $this->assertInstanceOf(Visitation::class, $visitation);
        $this->assertEquals($this->patient->id, $visitation->patient_id);
        $this->assertEquals($this->doctor->id, $visitation->doctor_id);
        $this->assertEquals('2025-04-22', $visitation->date);
        $this->assertEquals('09:15:00', $visitation->time);
        $this->assertEquals('Tension headache, possible migraine', $visitation->diagnosis);
    }

    /**
     * Test finding a visitation by ID
     */
    public function testFindVisitation()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        // Find the visitation by ID
        $found_visitation = Visitation::find($visitation->id);
        
        $this->assertInstanceOf(Visitation::class, $found_visitation);
        $this->assertEquals($visitation->id, $found_visitation->id);
        $this->assertEquals($visitation->patient_id, $found_visitation->patient_id);
        $this->assertEquals($visitation->doctor_id, $found_visitation->doctor_id);
        $this->assertEquals($visitation->date, $found_visitation->date);
    }

    /**
     * Test patient relationship
     */
    public function testPatientRelationship()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        // Get the related patient
        $patient = $visitation->patient();
        
        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals($this->patient->id, $patient->id);
        $this->assertEquals($this->patient->first_name, $patient->first_name);
        $this->assertEquals($this->patient->last_name, $patient->last_name);
    }

    /**
     * Test doctor relationship
     */
    public function testDoctorRelationship()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        // Get the related doctor
        $doctor = $visitation->doctor();
        
        $this->assertInstanceOf(Doctor::class, $doctor);
        $this->assertEquals($this->doctor->id, $doctor->id);
        $this->assertEquals($this->doctor->first_name, $doctor->first_name);
        $this->assertEquals($this->doctor->last_name, $doctor->last_name);
    }

    /**
     * Test lab investigations relationship
     */
    public function testLabInvestigationsRelationship()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        // Create a lab investigation for this visitation
        $lab = LabInvestigation::create([
            'visitation_id' => $visitation->id,
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'test_type' => 'Blood Test',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'notes' => 'Check for anemia'
        ]);
        
        // Get the related lab investigations
        $investigations = $visitation->labInvestigations();
        
        $this->assertIsArray($investigations);
        $this->assertNotEmpty($investigations);
        $this->assertInstanceOf(LabInvestigation::class, $investigations[0]);
        $this->assertEquals($lab->id, $investigations[0]->id);
        $this->assertEquals('Blood Test', $investigations[0]->test_type);
    }

    /**
     * Test updating a visitation
     */
    public function testUpdateVisitation()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'diagnosis' => 'Initial diagnosis'
        ]);
        
        // Update the visitation
        $visitation->diagnosis = 'Updated diagnosis after further tests';
        $visitation->treatment = 'Updated treatment plan';
        $visitation->save();
        
        // Retrieve the visitation again
        $updated = Visitation::find($visitation->id);
        
        $this->assertEquals('Updated diagnosis after further tests', $updated->diagnosis);
        $this->assertEquals('Updated treatment plan', $updated->treatment);
    }

    /**
     * Test deleting a visitation
     */
    public function testDeleteVisitation()
    {
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
        
        $visitation_id = $visitation->id;
        
        // Delete the visitation
        $visitation->delete();
        
        // Try to find the deleted visitation
        $deleted = Visitation::find($visitation_id);
        
        $this->assertNull($deleted);
    }
}
