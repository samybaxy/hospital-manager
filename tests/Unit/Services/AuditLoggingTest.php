<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\AuditLog;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;
use HospitalManager\Services\AuditLogger;
use Brain\Monkey\Functions;
use Mockery;

class AuditLoggingTest extends TestCase
{
    /**
     * @var int Admin user ID
     */
    protected $admin_user_id;
    
    /**
     * @var array Store mock audit logs
     */
    protected $mock_audit_logs = [];
    
    /**
     * @var int Next ID for mock audit logs
     */
    protected $next_audit_log_id = 1;
    
    /**
     * @var string User IP address for testing
     */
    protected $test_ip = '192.168.1.100';
    
    /**
     * @var string User agent for testing
     */
    protected $test_user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/90.0.4430.212 Safari/537.36';

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock logs
        $this->mock_audit_logs = [];
        $this->next_audit_log_id = 1;
        
        // Create a test admin user
        $this->admin_user_id = $this->createUserWithRole('administrator');
        
        // Mock WordPress functions
        Functions\when('wp_get_current_user')->justReturn((object)['ID' => $this->admin_user_id]);
        Functions\when('current_time')->justReturn('2025-04-22 10:30:00');
        
        // Mock request data
        Functions\when('wp_get_server_protocol')->justReturn('HTTP/1.1');
        
        // Mock user IP and agent
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_user_ip') {
                return $this->test_ip;
            } elseif ($tag === 'hospital_manager_user_agent') {
                return $this->test_user_agent;
            }
            return $value;
        });
        
        // Mock AuditLog::create
        Functions\when('AuditLog::create')->alias(function($data) {
            $id = $this->next_audit_log_id++;
            $this->mock_audit_logs[$id] = array_merge(['id' => $id], $data);
            return $this->createMockAuditLog($this->mock_audit_logs[$id]);
        });
        
        // Mock AuditLog::where to find logs
        Functions\when('AuditLog::where')->alias(function($column, $value = null) {
            $results = [];
            
            // Handle different where formats
            if (is_array($column)) {
                // Where with array of conditions
                foreach ($this->mock_audit_logs as $log) {
                    $match = true;
                    foreach ($column as $key => $val) {
                        if (!isset($log[$key]) || $log[$key] != $val) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $results[] = $this->createMockAuditLog($log);
                    }
                }
            } else {
                // Simple where with column and value
                foreach ($this->mock_audit_logs as $log) {
                    if (isset($log[$column]) && $log[$column] == $value) {
                        $results[] = $this->createMockAuditLog($log);
                    }
                }
            }
            
            return $results;
        });
        
        // Mock AuditLog::find
        Functions\when('AuditLog::find')->alias(function($id) {
            if (isset($this->mock_audit_logs[$id])) {
                return $this->createMockAuditLog($this->mock_audit_logs[$id]);
            }
            return null;
        });
        
        // Mock PatientService
        Functions\when('PatientService::createPatient')->alias(function($data) {
            // Create a mock patient
            $patient_id = rand(1000, 9999);
            $patient = Mockery::mock(Patient::class);
            $patient->id = $patient_id;
            $patient->first_name = $data['first_name'];
            $patient->last_name = $data['last_name'];
            
            // Log the patient creation action using WordPress-MVC pattern
            AuditLogger::log(
                'create_patient',
                'patient',
                $patient_id,
                ['patient_data' => $data]
            );
            
            return $patient;
        });
        
        // Mock PatientService::updatePatient
        Functions\when('PatientService::updatePatient')->alias(function($id, $data) {
            // Create a mock patient
            $patient = Mockery::mock(Patient::class);
            $patient->id = $id;
            
            // Update patient properties
            foreach ($data as $key => $value) {
                $patient->{$key} = $value;
            }
            
            // Log the patient update action
            AuditLogger::log(
                'update_patient',
                'patient',
                $id,
                [
                    'before' => ['first_name' => 'Old Name'],
                    'after' => $data
                ]
            );
            
            return $patient;
        });
        
        // Mock PatientService::deletePatient
        Functions\when('PatientService::deletePatient')->alias(function($id) {
            // Log the patient deletion
            AuditLogger::log(
                'delete_patient',
                'patient',
                $id,
                ['patient_id' => $id]
            );
            
            return true;
        });
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
     * Create a mock audit log object from data
     */
    private function createMockAuditLog($data)
    {
        $log = Mockery::mock(AuditLog::class);
        
        // Set up properties
        foreach ($data as $key => $value) {
            $log->{$key} = $value;
        }
        
        return $log;
    }
    
    /**
     * Get the count of audit logs
     */
    private function getAuditLogCount()
    {
        return count($this->mock_audit_logs);
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
        
        // There should be one new log entry
        $this->assertEquals(
            $log_count_before + 1, 
            $log_count_after,
            "Patient creation should generate exactly one audit log for compliance tracking"
        );
        
        // Find the log for this action
        $logs = AuditLog::where([
            'action' => 'create_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient->id
        ]);
        
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
            'phone_number' => '08055557777'
        ];
        $patient = PatientService::createPatient($patient_data);
        
        // Reset log count after creation
        $this->mock_audit_logs = [];
        
        // Update the patient
        $update_data = [
            'first_name' => 'Updated',
            'phone_number' => '08066667777'
        ];
        PatientService::updatePatient($patient->id, $update_data);
        
        // There should be one log entry for the update
        $this->assertEquals(1, $this->getAuditLogCount(), "Patient update should generate exactly one audit log entry");
        
        // Find the log for this action
        $logs = AuditLog::where([
            'action' => 'update_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient->id
        ]);
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Patient update audit log not found");
        $log = $logs[0];
        
        $this->assertEquals($this->admin_user_id, $log->user_id, "User ID in audit log doesn't match current user");
        $this->assertEquals($this->test_ip, $log->ip_address, "IP address not correctly recorded in audit log");
        
        // Check details contains before/after data for proper change tracking
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertArrayHasKey('before', $details, "Previous data missing from log details");
        $this->assertArrayHasKey('after', $details, "Updated data missing from log details");
        $this->assertEquals('Updated', $details['after']['first_name'], "Updated first name not properly logged");
    }
    
    /**
     * Test that patient deletion is properly logged
     */
    public function testPatientDeletionIsLogged()
    {
        // Create a patient first
        $patient_data = [
            'first_name' => 'Delete',
            'last_name' => 'TestPatient',
        ];
        $patient = PatientService::createPatient($patient_data);
        
        // Reset log count after creation
        $this->mock_audit_logs = [];
        
        // Delete the patient
        PatientService::deletePatient($patient->id);
        
        // There should be one log entry for the deletion
        $this->assertEquals(1, $this->getAuditLogCount(), "Patient deletion should generate exactly one audit log for tracking data removal");
        
        // Find the log for this action
        $logs = AuditLog::where([
            'action' => 'delete_patient',
            'entity_type' => 'patient',
            'entity_id' => $patient->id
        ]);
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Patient deletion audit log not found");
        $log = $logs[0];
        
        $this->assertEquals($this->admin_user_id, $log->user_id, "User ID in audit log doesn't match current user");
        $this->assertEquals($this->test_ip, $log->ip_address, "IP address not correctly recorded in audit log");
        
        // Check details contains patient id for tracking of deleted records
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertArrayHasKey('patient_id', $details, "Patient ID missing from deletion log");
        $this->assertEquals($patient->id, $details['patient_id'], "Deleted patient ID not properly logged");
    }
    
    /**
     * Test that sensitive actions are properly logged
     */
    public function testSensitiveActionLogging()
    {
        // Log a custom sensitive action
        AuditLogger::log(
            'view_patient_history',
            'patient',
            123,
            ['accessed_fields' => ['diagnosis', 'medications', 'lab_results']]
        );
        
        // Find the log for this action
        $logs = AuditLog::where([
            'action' => 'view_patient_history',
            'entity_type' => 'patient',
            'entity_id' => 123
        ]);
        
        // Verify log contents
        $this->assertNotEmpty($logs, "Sensitive action audit log not found");
        $log = $logs[0];
        
        $this->assertEquals($this->admin_user_id, $log->user_id, "User ID in audit log doesn't match current user");
        $this->assertEquals($this->test_ip, $log->ip_address, "IP address not correctly recorded in audit log");
        $this->assertEquals($this->test_user_agent, $log->user_agent, "User agent not correctly recorded in audit log");
        
        // Check details contains accessed fields for privacy tracking
        $details = json_decode($log->details, true);
        $this->assertIsArray($details, "Log details should be a valid JSON array");
        $this->assertArrayHasKey('accessed_fields', $details, "Accessed fields missing from log");
        $this->assertContains('diagnosis', $details['accessed_fields'], "Accessed sensitive field not properly logged");
    }
}
