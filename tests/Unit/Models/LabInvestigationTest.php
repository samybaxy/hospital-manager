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
