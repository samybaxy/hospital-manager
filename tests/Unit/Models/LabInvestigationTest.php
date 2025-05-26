<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Visitation;
use HospitalManager\Tests\Helpers\Debugger;

class LabInvestigationTest extends TestCase
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
     * @var Visitation Test visitation
     */
    protected $visitation;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test patient and doctor
        $this->patient = $this->createTestPatient();
        $this->doctor = $this->createTestDoctor();
        
        // Create a test visitation
        $this->visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID
        ]);
    }
    
    /**
     * Test lab investigation creation
     */
    public function testCreateLabInvestigation()
    {
        $data = [
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'test_type' => 'Complete Blood Count',
            'doctor_id' => $this->doctor->ID,
            'notes' => 'Check for infection markers',
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ];

        $lab = LabInvestigation::create($data);

        $this->assertInstanceOf(LabInvestigation::class, $lab);
        $this->assertEquals($this->visitation->ID, $lab->visitation_id);
        $this->assertEquals($this->patient->ID, $lab->patient_id);
        $this->assertEquals('Complete Blood Count', $lab->test_type);
        $this->assertEquals($this->doctor->ID, $lab->doctor_id);
        $this->assertEquals('pending', $lab->status);
    }

    /**
     * Test finding a lab investigation by ID
     */
    public function testFindLabInvestigation()
    {
        // Create a test lab investigation
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID
        ]);
        
        // Find the lab investigation by ID
        $found_lab = LabInvestigation::find($lab->ID);
        
        $this->assertInstanceOf(LabInvestigation::class, $found_lab);
        $this->assertEquals($lab->ID, $found_lab->ID);
        $this->assertEquals($lab->visitation_id, $found_lab->visitation_id);
        $this->assertEquals($lab->patient_id, $found_lab->patient_id);
        $this->assertEquals($lab->test_type, $found_lab->test_type);
    }

    /**
     * Test visitation relationship
     */
    public function testVisitationRelationship()
    {
        // Create a test lab investigation
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID
        ]);
        
        // Get the related visitation
        $visitation = $lab->visitation();
        
        $this->assertInstanceOf(Visitation::class, $visitation);
        $this->assertEquals($this->visitation->ID, $visitation->ID);
        $this->assertEquals($this->visitation->patient_id, $visitation->patient_id);
        $this->assertEquals($this->visitation->doctor_id, $visitation->doctor_id);
    }

    /**
     * Test doctor relationship (requestedBy)
     */
    public function testRequestedByRelationship()
    {
        // Create a test lab investigation
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID
        ]);
        
        // Get the related doctor
        $doctor = $lab->requestedBy();
        
        $this->assertInstanceOf(Doctor::class, $doctor);
        $this->assertEquals($this->doctor->ID, $doctor->ID);
    }

    /**
     * Test updating a lab investigation
     */
    public function testUpdateLabInvestigation()
    {
        // Create a test lab investigation
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID,
            'status' => 'pending'
        ]);

        // Update the lab investigation with results
        $lab->results = 'Normal blood count, hemoglobin 14.2 g/dL';
        $lab->status = 'completed';
        $lab->save();

        // Retrieve the lab investigation again
        $updated = LabInvestigation::find($lab->ID);
        
        $this->assertEquals('completed', $updated->status);
        $this->assertEquals('Normal blood count, hemoglobin 14.2 g/dL', $updated->results);
    }

    /**
     * Test deleting a lab investigation
     */
    public function testDeleteLabInvestigation()
    {
        // Create a test lab investigation
        $lab = $this->createTestLabInvestigation([
            'visitation_id' => $this->visitation->ID,
            'patient_id' => $this->patient->ID,
            'doctor_id' => $this->doctor->ID
        ]);
        
        $lab_id = $lab->ID;
        
        // Delete the lab investigation
        $lab->delete();
        
        // Try to find the deleted lab investigation
        $deleted = LabInvestigation::find($lab_id);
        
        $this->assertNull($deleted);
    }
}
