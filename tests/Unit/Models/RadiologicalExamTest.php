<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\RadiologicalExam;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Visitation;
use WPMVC\MVC\Models\UserModel;

class RadiologicalExamTest extends TestCase
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
     * @var int Test technician user ID
     */
    protected $tech_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create test users
        $patient_user_id = $this->createUserWithRole('patient');
        $doctor_user_id = $this->createUserWithRole('doctor');
        $this->tech_id = $this->createUserWithRole('lab_tech');
        
        // Create a test patient and doctor
        $this->patient = $this->createTestPatient([
            'user_id' => $patient_user_id
        ]);
        
        $this->doctor = $this->createTestDoctor([
            'user_id' => $doctor_user_id
        ]);
        
        // Create a test visitation
        $this->visitation = $this->createTestVisitation([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id
        ]);
    }
    
    /**
     * Test radiological exam creation
     */
    public function testCreateRadiologicalExam()
    {
        $data = [
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id,
            'results' => 'X-ray shows no bone fractures. Soft tissues appear normal.'
        ];

        $exam = RadiologicalExam::create($data);

        $this->assertInstanceOf(RadiologicalExam::class, $exam);
        $this->assertEquals($this->visitation->id, $exam->visitation_id);
        $this->assertEquals($this->tech_id, $exam->tech_id);
        $this->assertEquals('X-ray shows no bone fractures. Soft tissues appear normal.', $exam->results);
    }

    /**
     * Test finding a radiological exam by ID
     */
    public function testFindRadiologicalExam()
    {
        // Create a test exam
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id
        ]);
        
        // Find the exam by ID
        $found_exam = RadiologicalExam::find($exam->id);
        
        $this->assertInstanceOf(RadiologicalExam::class, $found_exam);
        $this->assertEquals($exam->id, $found_exam->id);
        $this->assertEquals($exam->visitation_id, $found_exam->visitation_id);
        $this->assertEquals($exam->tech_id, $found_exam->tech_id);
    }

    /**
     * Test visitation relationship
     */
    public function testVisitationRelationship()
    {
        // Create a test exam
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id
        ]);
        
        // Get the related visitation
        $visitation = $exam->visitation();
        
        $this->assertInstanceOf(Visitation::class, $visitation);
        $this->assertEquals($this->visitation->id, $visitation->id);
        $this->assertEquals($this->visitation->patient_id, $visitation->patient_id);
        $this->assertEquals($this->visitation->doctor_id, $visitation->doctor_id);
    }

    /**
     * Test technician relationship
     */
    public function testTechnicianRelationship()
    {
        // Create a test exam
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id
        ]);
        
        // Get the related technician
        $technician = $exam->technician();
        
        $this->assertInstanceOf(\WP_User::class, $technician);
        $this->assertEquals($this->tech_id, $technician->ID);
    }

    /**
     * Test updating a radiological exam
     */
    public function testUpdateRadiologicalExam()
    {
        // Create a test exam
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id,
            'results' => 'Initial results'
        ]);
        
        // Update the results
        $exam->results = 'Updated results after secondary review';
        $exam->save();
        
        // Retrieve the exam again
        $updated_exam = RadiologicalExam::find($exam->id);
        
        $this->assertEquals('Updated results after secondary review', $updated_exam->results);
    }

    /**
     * Test deleting a radiological exam
     */
    public function testDeleteRadiologicalExam()
    {
        // Create a test exam
        $exam = $this->createTestRadiologicalExam([
            'visitation_id' => $this->visitation->id,
            'tech_id' => $this->tech_id
        ]);
        
        $exam_id = $exam->id;
        
        // Delete the exam
        $exam->delete();
        
        // Try to find the deleted exam
        $deleted_exam = RadiologicalExam::find($exam_id);
        
        $this->assertNull($deleted_exam);
    }
}
