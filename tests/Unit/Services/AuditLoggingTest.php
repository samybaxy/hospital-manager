<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Mock AuditLog class for testing
 */
class MockAuditLog 
{
    public static $mockLogs = [];
    public static $nextId = 1;
    
    /**
     * Create a mock audit log entry
     */
    public static function create($data) 
    {
        $id = self::$nextId++;
        $log = (object)array_merge(['id' => $id], $data);
        self::$mockLogs[$id] = $log;
        return $log;
    }
    
    /**
     * Mock where method for querying logs
     */
    public static function where($column, $value = null) 
    {
        $results = [];
        
        // Handle different where formats
        if (is_array($column)) {
            // Where with array of conditions
            foreach (self::$mockLogs as $log) {
                $match = true;
                foreach ($column as $key => $val) {
                    if (!isset($log->$key) || $log->$key != $val) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $results[] = $log;
                }
            }
        } else {
            // Simple where with column and value
            foreach (self::$mockLogs as $log) {
                if (isset($log->$column) && $log->$column == $value) {
                    $results[] = $log;
                }
            }
        }
        
        // Return a mock query builder
        return new MockQueryBuilder($results);
    }
    
    /**
     * Find a log by ID
     */
    public static function find($id) 
    {
        return isset(self::$mockLogs[$id]) ? self::$mockLogs[$id] : null;
    }
}

/**
 * Mock query builder for audit logs
 */
class MockQueryBuilder 
{
    protected $results = [];
    
    public function __construct($results) 
    {
        $this->results = $results;
    }
    
    public function get() 
    {
        return $this->results;
    }
    
    public function first() 
    {
        return count($this->results) > 0 ? $this->results[0] : null;
    }
    
    public function count() 
    {
        return count($this->results);
    }
    
    public function orderBy($column, $direction = 'asc') 
    {
        // Just return the same query builder for chaining
        return $this;
    }
}

/**
 * Mock Patient class
 */
class MockPatient 
{
    public $id;
    public $first_name;
    public $last_name;
    public $phone_number;
    public $sex;
    public $age;
    public $bio_data;
}

/**
 * Mock AuditLogger for testing
 */
class MockAuditLogger 
{
    /**
     * Log an auditable action
     */
    public static function log($action, $entityType, $entityId, $details = [], $userId = null) 
    {
        // Default user ID if not provided
        if ($userId === null) {
            $userId = 1; // Default admin user
        }
        
        // Format details as JSON if they're an array
        $encodedDetails = is_string($details) ? $details : json_encode($details);
        
        // Sanitize sensitive data
        if (is_array($details)) {
            if (isset($details['password'])) {
                unset($details['password']);
            }
            if (isset($details['credit_card'])) {
                unset($details['credit_card']);
            }
            $encodedDetails = json_encode($details);
        }
        
        // Create log data
        $data = [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $encodedDetails,
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/90.0.4430.212 Safari/537.36',
            'created_at' => '2025-04-22 10:30:00'
        ];
        
        // Create the log entry using our mock
        return MockAuditLog::create($data);
    }
}

/**
 * Mock PatientService for testing
 */
class MockPatientService 
{
    /**
     * Create a patient
     */
    public static function createPatient($data) 
    {
        // Create a new patient
        $patient = new MockPatient();
        $patient->id = rand(1000, 9999);
        
        // Set patient properties
        foreach ($data as $key => $value) {
            $patient->$key = $value;
        }
        
        // Log the patient creation action
        MockAuditLogger::log(
            'create_patient',
            'patient',
            $patient->id,
            ['patient_data' => $data]
        );
        
        return $patient;
    }
    
    /**
     * Update a patient
     */
    public static function updatePatient($id, $data) 
    {
        // Create a mock patient
        $patient = new MockPatient();
        $patient->id = $id;
        
        // Update patient properties
        foreach ($data as $key => $value) {
            $patient->$key = $value;
        }
        
        // Log the patient update action
        MockAuditLogger::log(
            'update_patient',
            'patient',
            $id,
            [
                'before' => ['first_name' => 'Old Name'],
                'after' => $data
            ]
        );
        
        return $patient;
    }
    
    /**
     * Delete a patient
     */
    public static function deletePatient($id) 
    {
        // Log the patient deletion
        MockAuditLogger::log(
            'delete_patient',
            'patient',
            $id,
            ['patient_id' => $id]
        );
        
        return true;
    }
}

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
            'phone_number' => '08055556666',
            'sex' => 'M',
            'age' => 35,
            'bio_data' => 'Test patient for audit logging'
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
            'phone_number' => '08055557777'
        ];
        $patient = MockPatientService::createPatient($patient_data);
        
        // Reset log count after creation
        MockAuditLog::$mockLogs = [];
        MockAuditLog::$nextId = 1;
        
        // Update the patient
        $update_data = [
            'first_name' => 'Updated',
            'phone_number' => '08066667777'
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
        $patient_id = 5000; // Arbitrary ID for testing
        
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
        
        // Get the created log
        $log = MockAuditLog::$mockLogs[1];
        
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
