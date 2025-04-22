<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\AuditLog;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;

class AuditLoggingTest extends TestCase
{
    /**
     * @var int
     */
    protected $admin_user_id;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test admin user
        $this->admin_user_id = $this->createUserWithRole('administrator');
        wp_set_current_user($this->admin_user_id);
    }

    /**
     * Test that patient creation is properly logged
     */
    public function testPatientCreationIsLogged()
    {
        $patient_data = [
            'first_name' => 'Audit',
            'last_name' => 'TestCreate',
            'phone_number' => '08055556666',
            'sex' => 'M',
            'age' => 35,
            'bio_data' => 'Test patient for audit logging'
        ];
        
        // Count audit logs before
        $log_count_before = $this->getAuditLogCount();
        
        // Create a patient
        $patient = PatientService::createPatient($patient_data);
        
        // Count audit logs after
        $log_count_after = $this->getAuditLogCount();
        
        // Verify that a log entry was created
        $this->assertEquals($log_count_before + 1, $log_count_after);
        
        // Get the latest log
        $log = $this->getLatestAuditLog();
        
        // Verify log details
        $this->assertEquals('patient', $log->entity_type);
        $this->assertEquals($patient->id, $log->entity_id);
        $this->assertEquals('create', $log->action);
        $this->assertEquals($this->admin_user_id, $log->user_id);
    }

    /**
     * Test that patient updates are properly logged
     */
    public function testPatientUpdateIsLogged()
    {
        // Create a test patient
        $patient = $this->createTestPatient([
            'first_name' => 'Audit',
            'last_name' => 'TestUpdate'
        ]);
        
        // Reset audit log count after creation
        $log_count_before = $this->getAuditLogCount();
        
        // Update the patient
        $updated_data = [
            'bio_data' => 'Updated patient data for audit test',
            'age' => 40
        ];
        
        PatientService::updatePatient($patient->id, $updated_data);
        
        // Count audit logs after update
        $log_count_after = $this->getAuditLogCount();
        
        // Verify that a log entry was created
        $this->assertEquals($log_count_before + 1, $log_count_after);
        
        // Get the latest log
        $log = $this->getLatestAuditLog();
        
        // Verify log details
        $this->assertEquals('patient', $log->entity_type);
        $this->assertEquals($patient->id, $log->entity_id);
        $this->assertEquals('update', $log->action);
        $this->assertEquals($this->admin_user_id, $log->user_id);
        
        // Check that changes were recorded
        $changes = json_decode($log->changes, true);
        $this->assertIsArray($changes);
        $this->assertArrayHasKey('age', $changes);
        $this->assertEquals(40, $changes['age']['new']);
    }

    /**
     * Test that patient deletion is properly logged
     */
    public function testPatientDeletionIsLogged()
    {
        // Create a test patient
        $patient = $this->createTestPatient([
            'first_name' => 'Audit',
            'last_name' => 'TestDelete'
        ]);
        
        // Store patient ID for later verification
        $patient_id = $patient->id;
        
        // Reset audit log count after creation
        $log_count_before = $this->getAuditLogCount();
        
        // Delete the patient
        $patient->delete();
        
        // Count audit logs after deletion
        $log_count_after = $this->getAuditLogCount();
        
        // Verify that a log entry was created
        $this->assertEquals($log_count_before + 1, $log_count_after);
        
        // Get the latest log
        $log = $this->getLatestAuditLog();
        
        // Verify log details
        $this->assertEquals('patient', $log->entity_type);
        $this->assertEquals($patient_id, $log->entity_id);
        $this->assertEquals('delete', $log->action);
        $this->assertEquals($this->admin_user_id, $log->user_id);
    }

    /**
     * Test audit log accuracy with multiple users
     */
    public function testAuditLogAccuracyWithDifferentUsers()
    {
        // Create test users with different roles
        $doctor_id = $this->createUserWithRole('doctor');
        $receptionist_id = $this->createUserWithRole('receptionist');
        
        // 1. Create a patient as admin
        wp_set_current_user($this->admin_user_id);
        $patient = $this->createTestPatient(['first_name' => 'Audit', 'last_name' => 'MultiUser']);
        $latest_log = $this->getLatestAuditLog();
        $this->assertEquals($this->admin_user_id, $latest_log->user_id);
        
        // 2. Update as doctor
        wp_set_current_user($doctor_id);
        PatientService::updatePatient($patient->id, ['bio_data' => 'Updated by doctor']);
        $latest_log = $this->getLatestAuditLog();
        $this->assertEquals($doctor_id, $latest_log->user_id);
        $this->assertEquals('update', $latest_log->action);
        
        // 3. Update as receptionist
        wp_set_current_user($receptionist_id);
        PatientService::updatePatient($patient->id, ['phone_number' => '08099997777']);
        $latest_log = $this->getLatestAuditLog();
        $this->assertEquals($receptionist_id, $latest_log->user_id);
        $this->assertEquals('update', $latest_log->action);
    }

    /**
     * Test searching audit logs
     */
    public function testAuditLogSearching()
    {
        // Create multiple patients to generate audit logs
        $patient1 = $this->createTestPatient(['first_name' => 'LogSearch1']);
        $patient2 = $this->createTestPatient(['first_name' => 'LogSearch2']);
        
        // Update both patients
        PatientService::updatePatient($patient1->id, ['age' => 25]);
        PatientService::updatePatient($patient2->id, ['age' => 35]);
        
        // Search logs by entity type
        $patient_logs = AuditLog::where('entity_type', 'patient')->get();
        $this->assertNotEmpty($patient_logs);
        
        // Search logs by action
        $update_logs = AuditLog::where('action', 'update')->get();
        $this->assertCount(2, $update_logs);
        
        // Search logs by entity ID
        $patient1_logs = AuditLog::where('entity_id', $patient1->id)->get();
        $this->assertGreaterThanOrEqual(2, count($patient1_logs)); // Create + update
        
        // Search logs by date range
        $today_logs = AuditLog::where('created_at', '>=', date('Y-m-d'))->get();
        $this->assertNotEmpty($today_logs);
    }

    /**
     * Helper method to get the total count of audit logs
     */
    private function getAuditLogCount()
    {
        return AuditLog::count();
    }

    /**
     * Helper method to get the latest audit log
     */
    private function getLatestAuditLog()
    {
        $logs = AuditLog::orderBy('created_at', 'DESC')->get();
        return $logs[0] ?? null;
    }
}
