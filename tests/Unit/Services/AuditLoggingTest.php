<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use HospitalManager\Tests\Mocks\Services\MockAuditLog;
use HospitalManager\Tests\Mocks\Services\MockAuditLogger;
use HospitalManager\Tests\Mocks\Services\MockPatientService;
use Mockery;

/**
 * Test for AuditLogging functionality
 */
class AuditLoggingTest extends TestCase
{
    /**
     * @var int Admin user ID
     */
    protected $admin_user_id = 1;
    
    /**
     * @var string User IP address for testing
     */
    protected $test_ip = '127.0.0.1';
    
    /**
     * @var string User agent for testing
     */
    protected $test_user_agent = 'PHPUnit Test';

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock logs
        MockAuditLog::$mockLogs = [];
        MockAuditLog::$nextId = 1;
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Get the count of audit logs
     */
    private function getAuditLogCount()
    {
        return count(MockAuditLog::$mockLogs);
    }

    /**
     * Test that patient creation is properly logged
     */
    public function testPatientCreationIsLogged()
    {
        $patient_data = [
            'first_name' => 'Audit',
            'last_name' => 'TestCreate',
            'phone' => '08055556666',
            'gender' => 'M',
            'age' => 35,
            'bio_data' => json_encode(['Test patient for audit logging'])
        ];
        
        // Count audit logs before
        $log_count_before = $this->getAuditLogCount();
        
        // Create a patient
        $patient = MockPatientService::createPatient($patient_data);
        
        // Count audit logs after
        $log_count_after = $this->getAuditLogCount();
        
        // There should be one new log entry
        $this->assertEquals(
            $log_count_before + 1, 
            $log_count_after,
            "Patient creation should generate exactly one audit log for compliance tracking"
        );
        
        // Find the log for this action
        $logs = MockAuditLog::where([
            'action' => 'create_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient->id
        ])->get();
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Patient creation audit log not found");
        $log = $logs[0];
        
        $this->assertEquals($this->admin_user_id, $log->user_id, "User ID in audit log doesn't match current user");
        $this->assertEquals('create_patient', $log->action, "Incorrect action recorded in audit log");
        $this->assertEquals('patient', $log->entity_type, "Incorrect entity type in audit log");
        $this->assertEquals($patient->id, $log->entity_id, "Incorrect entity ID in audit log");
        $this->assertEquals($this->test_ip, $log->ip_address, "IP address not correctly recorded in audit log");
        $this->assertNotNull($log->created_at, "Timestamp missing from audit log");
        
        // Check details contains patient data
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertArrayHasKey('patient_data', $details, "Patient data missing from log details");
        $this->assertEquals($patient_data['first_name'], $details['patient_data']['first_name'], "Patient first name not properly logged");
    }

    /**
     * Test that patient updates are properly logged
     */
    public function testPatientUpdateIsLogged()
    {
        // Create a patient first
        $patient_data = [
            'first_name' => 'Update',
            'last_name' => 'TestPatient',
            'phone' => '08055557777',
            'gender' => 'M',
            'age' => 35,
            'bio_data' => 'Initial bio data for update test'
        ];
        $patient = MockPatientService::createPatient($patient_data);
        
        // Reset log count after creation
        MockAuditLog::$mockLogs = [];
        MockAuditLog::$nextId = 1;
        
        // Update the patient
        $update_data = [
            'first_name' => 'Updated',
            'phone' => '08066667777',
            // Required fields to satisfy validation
            'last_name' => 'Smith',
            'gender' => 'M',
            'age' => 35,
            'bio_data' => 'Updated bio data'
        ];
        MockPatientService::updatePatient($patient->id, $update_data);
        
        // There should be one log entry for the update
        $this->assertEquals(1, $this->getAuditLogCount(), "Patient update should generate exactly one audit log entry");
        
        // Find the log for this action
        $logs = MockAuditLog::where([
            'action' => 'update_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient->id
        ])->get();
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Patient update audit log not found");
        $log = $logs[0];
        
        // Check log fields
        $this->assertEquals('update_patient', $log->action, "Incorrect action recorded in audit log");
        
        // Check that before/after data is present
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertArrayHasKey('before', $details, "Before data missing from log details");
        $this->assertArrayHasKey('after', $details, "After data missing from log details");
        $this->assertEquals($update_data['first_name'], $details['after']['first_name'], "Updated first name not properly logged");
    }
    
    /**
     * Test that patient deletion is properly logged
     */
    public function testPatientDeletionIsLogged()
    {
        // First create a patient that we can delete
        $patient_data = [
            'first_name' => 'Delete',
            'last_name' => 'Patient',
            'phone' => '08012345678',
            'gender' => 'M',
            'age' => 42,
            'bio_data' => 'Delete patient bio data'
        ];
        
        // Create and get the ID
        $patient = MockPatientService::createPatient($patient_data);
        $patient_id = $patient->id;
        
        // Reset log count after creation
        MockAuditLog::$mockLogs = [];
        MockAuditLog::$nextId = 1;
        
        // Delete the patient
        MockPatientService::deletePatient($patient_id);
        
        // Find the log for this action
        $logs = MockAuditLog::where([
            'action' => 'delete_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient_id
        ])->get();
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Patient deletion audit log not found");
        $log = $logs[0];
        
        // Check log fields
        $this->assertEquals('delete_patient', $log->action, "Incorrect action recorded in audit log");
        $this->assertEquals($patient_id, $log->entity_id, "Incorrect patient ID in deletion log");
        
        // Check details contains patient ID
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertEquals($patient_id, $details['patient_id'], "Patient ID not properly logged in deletion details");
    }
    
    /**
     * Test that sensitive data is properly filtered
     */
    public function testSensitiveDataIsSanitized()
    {
        // Create log with sensitive data
        $sensitive_data = [
            'username' => 'testuser',
            'password' => 'supersecretpassword',
            'credit_card' => '4111-1111-1111-1111',
            'notes' => 'Test notes'
        ];
        
        // Log an action with sensitive data
        MockAuditLogger::log('user_login', 'user', 123, $sensitive_data);
        
        // Find the log for this action
        $logs = MockAuditLog::where([
            'action' => 'user_login',
            'entity_type' => 'user',
            'entity_id' => 123
        ])->get();
        
        // Verify log exists
        $this->assertNotEmpty($logs, "User login audit log not found");
        $log = $logs[0];
        
        // Check that sensitive data was sanitized
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        
        // Check that sensitive fields were removed
        $this->assertArrayNotHasKey('password', $details, "Password should be sanitized from audit log");
        $this->assertArrayNotHasKey('credit_card', $details, "Credit card should be sanitized from audit log");
        
        // Check that non-sensitive data remains
        $this->assertArrayHasKey('username', $details, "Non-sensitive data should remain in audit log");
        $this->assertEquals('testuser', $details['username'], "Non-sensitive username should be preserved");
        $this->assertArrayHasKey('notes', $details, "Non-sensitive notes should remain in audit log");
    }
}
