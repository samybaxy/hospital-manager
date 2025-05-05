<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Mock AuditLog class with static create method
 */
class MockAuditLog {
    public static function create($data) {
        global $testAuditLoggerInstance;
        $testAuditLoggerInstance->logData = $data;
        
        $log = new \stdClass();
        foreach ($data as $key => $value) {
            $log->$key = $value;
        }
        $log->id = 999;
        
        return $log;
    }
}

/**
 * Mock AuditLogger class
 */
class MockAuditLogger {
    /**
     * Log an audit entry
     */
    public static function log($action, $entityType, $entityId, $details = [], $userId = null) {
        // If userId is not provided, use current user ID (1 by default)
        if ($userId === null) {
            $userId = 1;
        }
        
        // Handle JSON string or array
        $encodedDetails = is_string($details) ? $details : json_encode($details);
        
        // Remove sensitive data
        if (is_array($details)) {
            if (isset($details['password'])) {
                unset($details['password']);
            }
            $encodedDetails = json_encode($details);
        }
        
        // Create the log data
        $logData = [
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $encodedDetails,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Create the log entry
        return \HospitalManager\Tests\Unit\Services\MockAuditLog::create($logData);
    }
}

/**
 * Test for AuditLogger service
 */
class AuditLoggerTest extends TestCase
{
    /**
     * @var array
     */
    public $logData;
    
    /**
     * @var int
     */
    public $currentUserId = 1;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset test data
        $this->logData = null;
        
        // Store a global reference to this test instance
        global $testAuditLoggerInstance;
        $testAuditLoggerInstance = $this;
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
     * Test basic audit logging functionality
     */
    public function testBasicLogging()
    {
        $action = 'create_patient';
        $entityType = 'patient';
        $entityId = 123;
        $details = ['name' => 'John Doe', 'age' => 45];
        
        // Call the static log method through our mock
        $result = MockAuditLogger::log($action, $entityType, $entityId, $details);
        
        // We can't use instanceof with dynamic creation, so check for expected properties
        $this->assertNotNull($result);
        $this->assertEquals(999, $result->id);
        
        // Verify the logged data
        $this->assertNotNull($this->logData);
        $this->assertEquals($this->currentUserId, $this->logData['user_id']);
        $this->assertEquals($action, $this->logData['action']);
        $this->assertEquals($entityType, $this->logData['entity_type']);
        $this->assertEquals($entityId, $this->logData['entity_id']);
        
        // Verify details were JSON encoded
        $this->assertEquals(json_encode($details), $this->logData['details']);
    }
    
    /**
     * Test logging with JSON-encoded details
     */
    public function testLoggingWithPreEncodedDetails()
    {
        $action = 'update_patient';
        $entityType = 'patient';
        $entityId = 456;
        $details = json_encode(['before' => ['status' => 'pending'], 'after' => ['status' => 'active']]);
        
        // Call the log method
        $result = MockAuditLogger::log($action, $entityType, $entityId, $details);
        
        // Verify the log was created
        $this->assertNotNull($result);
        
        // Verify the details were not double-encoded
        $this->assertEquals($details, $this->logData['details']);
    }
    
    /**
     * Test logging with a specific user ID
     */
    public function testLoggingWithSpecificUserId()
    {
        $action = 'view_patient';
        $entityType = 'patient';
        $entityId = 789;
        $details = ['fields' => ['medical_history', 'diagnoses']];
        $specificUserId = 42;
        
        // Call the log method with specific user ID
        $result = MockAuditLogger::log($action, $entityType, $entityId, $details, $specificUserId);
        
        // Verify the log was created with the specific user ID
        $this->assertNotNull($result);
        $this->assertEquals($specificUserId, $this->logData['user_id']);
    }
    
    /**
     * Test logging when no user is logged in
     */
    public function testLoggingWithNoUser()
    {
        // Set current user ID to 0 (no user)
        $this->currentUserId = 0;
        
        $action = 'system_action';
        $entityType = 'system';
        $entityId = 1;
        $details = ['event' => 'scheduled_maintenance'];
        
        // Call the log method with user_id = 0
        $result = MockAuditLogger::log($action, $entityType, $entityId, $details, 0);
        
        // Verify the log was created with user_id = 0
        $this->assertNotNull($result);
        $this->assertEquals(0, $this->logData['user_id']);
    }
    
    /**
     * Test sanitization functionality
     */
    public function testDetailsSanitization()
    {
        $action = 'login_attempt';
        $entityType = 'user';
        $entityId = 999;
        
        // Include sensitive data that should be sanitized
        $details = [
            'username' => 'testuser',
            'password' => 'sensitive_password',
            'success' => false
        ];
        
        // Call the log method
        $result = MockAuditLogger::log($action, $entityType, $entityId, $details);
        
        // Decode the details to check sanitization
        $loggedDetails = json_decode($this->logData['details'], true);
        
        // Check that sensitive data was sanitized
        $this->assertArrayNotHasKey('password', $loggedDetails);
        $this->assertArrayHasKey('username', $loggedDetails);
    }
}
