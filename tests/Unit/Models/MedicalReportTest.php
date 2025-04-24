<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\MedicalReport;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Models\Visitation;

class MedicalReportTest extends TestCase
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
        
        // Create test users
        $patient_user_id = $this->createUserWithRole('patient');
        $doctor_user_id = $this->createUserWithRole('doctor');
        
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
     * Test medical report creation
     */
    public function testCreateMedicalReport()
    {
        $data = [
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id,
            'report_content' => 'Patient presented with symptoms of acute sinusitis. Prescribed antibiotics and rest.',
            'status' => 'completed',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $report = MedicalReport::create($data);

        $this->assertInstanceOf(MedicalReport::class, $report);
        $this->assertEquals($this->patient->id, $report->patient_id);
        $this->assertEquals($this->doctor->id, $report->doctor_id);
        $this->assertEquals($this->visitation->id, $report->visitation_id);
        $this->assertEquals('completed', $report->status);
        $this->assertNotNull($report->created_at);
    }

    /**
     * Test finding a medical report by ID
     */
    public function testFindMedicalReport()
    {
        // Create a test report
        $report = $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id
        ]);
        
        // Find the report by ID
        $found_report = MedicalReport::find($report->id);
        
        $this->assertInstanceOf(MedicalReport::class, $found_report);
        $this->assertEquals($report->id, $found_report->id);
        $this->assertEquals($report->patient_id, $found_report->patient_id);
        $this->assertEquals($report->doctor_id, $found_report->doctor_id);
        $this->assertEquals($report->visitation_id, $found_report->visitation_id);
    }

    /**
     * Test getting pending reports for a doctor
     */
    public function testGetPendingForDoctor()
    {
        // Create multiple reports with different statuses
        $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id,
            'status' => 'pending'
        ]);
        
        $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id,
            'status' => 'pending'
        ]);
        
        $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id,
            'status' => 'completed'
        ]);
        
        // Get pending reports
        $pending_reports = MedicalReport::getPendingForDoctor($this->doctor->id);
        
        $this->assertNotEmpty($pending_reports);
        $this->assertCount(2, $pending_reports);
        foreach ($pending_reports as $report) {
            $this->assertEquals('pending', $report->status);
            $this->assertEquals($this->doctor->id, $report->doctor_id);
        }
    }

    /**
     * Test getting pending reports with limit
     */
    public function testGetPendingForDoctorWithLimit()
    {
        // Create multiple pending reports
        for ($i = 0; $i < 5; $i++) {
            $this->createTestMedicalReport([
                'patient_id' => $this->patient->id,
                'doctor_id' => $this->doctor->id,
                'visitation_id' => $this->visitation->id,
                'status' => 'pending'
            ]);
        }
        
        // Get pending reports with limit 3
        $limited_reports = MedicalReport::getPendingForDoctor($this->doctor->id, 3);
        
        $this->assertNotEmpty($limited_reports);
        $this->assertCount(3, $limited_reports);
        foreach ($limited_reports as $report) {
            $this->assertEquals('pending', $report->status);
        }
    }

    /**
     * Test updating a medical report
     */
    public function testUpdateMedicalReport()
    {
        // Create a test report
        $report = $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id,
            'status' => 'pending',
            'report_content' => 'Initial draft of report'
        ]);
        
        // Update the report
        $report->status = 'completed';
        $report->report_content = 'Final version of the report after review';
        $report->updated_at = current_time('mysql');
        $report->save();
        
        // Retrieve the report again
        $updated_report = MedicalReport::find($report->id);
        
        $this->assertEquals('completed', $updated_report->status);
        $this->assertEquals('Final version of the report after review', $updated_report->report_content);
    }

    /**
     * Test deleting a medical report
     */
    public function testDeleteMedicalReport()
    {
        // Create a test report
        $report = $this->createTestMedicalReport([
            'patient_id' => $this->patient->id,
            'doctor_id' => $this->doctor->id,
            'visitation_id' => $this->visitation->id
        ]);
        
        $report_id = $report->id;
        
        // Delete the report
        $report->delete();
        
        // Try to find the deleted report
        $deleted_report = MedicalReport::find($report_id);
        
        $this->assertNull($deleted_report);
    }
}
