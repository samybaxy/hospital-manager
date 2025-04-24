<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\AuditLogger;
use HospitalManager\Models\AuditLog;
use Brain\Monkey\Functions;
use Mockery;

class AuditLoggerTest extends TestCase
{
    /**
     * @var int Mock user ID for testing
     */
    private $test_user_id = 1;
    
    /**
     * @var array Store created audit log data for assertions
     */
    private $mock_audit_log_data = null;
    
    /**
     * @var string Mock IP address for testing
     */
    private $mock_ip = '192.168.1.100';
    
    /**
     * @var string Mock user agent for testing
     */
    private $mock_user_agent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/101.0.4951.54 Safari/537.36';
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        $this->mock_audit_log_data = null;
        
        // Mock WordPress user functions
        Functions\when('get_current_user_id')->justReturn($this->test_user_id);
        Functions\when('wp_get_current_user')->justReturn((object)['ID' => $this->test_user_id]);
        Functions\when('current_time')->justReturn('2025-04-22 10:00:00');
        Functions\when('error_log')->justReturn(true);
        
        // Mock the server environment using WordPress-MVC pattern with filters
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_user_ip') {
                return $this->mock_ip;
            } elseif ($tag === 'hospital_manager_user_agent') {
                return $this->mock_user_agent;
            } elseif ($tag === 'pre_audit_log_create') {
                // Allow pre-filtering of audit log data
                return $value;
            } elseif ($tag === 'after_audit_log_create') {
                // Handle post-creation filtering
                return $args[0];
            }
            return $value;
        });
        
        // Mock the AuditLog::create method
        Functions\when('AuditLog::create')->alias(function($data) {
            $this->mock_audit_log_data = $data;
            
            // Create a mock AuditLog that matches WordPress-MVC pattern
            $log = Mockery::mock(AuditLog::class);
            
            // Set up properties
            foreach (array_merge(['id' => 999], $data) as $key => $value) {
                $log->{$key} = $value;
            }
            
            return $log;
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
     * Test basic audit logging functionality
     */
    public function testBasicLogging()
    {
        $action = 'create_patient';
        $entityType = 'patient';
        $entityId = 123;
        $details = ['name' => 'John Doe', 'age' => 45];
        
        // Perform the logging
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Verify the log was created
        $this->assertInstanceOf(AuditLog::class, $result, "Audit logger should return an AuditLog instance");
        
        // Verify the logged data
        $this->assertNotNull($this->mock_audit_log_data, "AuditLog::create should be called with log data");
        $this->assertEquals($this->test_user_id, $this->mock_audit_log_data['user_id'], "User ID should be the current user");
        $this->assertEquals($action, $this->mock_audit_log_data['action'], "Action should match the specified action");
        $this->assertEquals($entityType, $this->mock_audit_log_data['entity_type'], "Entity type should match the specified type");
        $this->assertEquals($entityId, $this->mock_audit_log_data['entity_id'], "Entity ID should match the specified ID");
        $this->assertEquals($this->mock_ip, $this->mock_audit_log_data['ip_address'], "IP address should be recorded");
        $this->assertEquals($this->mock_user_agent, $this->mock_audit_log_data['user_agent'], "User agent should be recorded");
        
        // Verify the details were properly JSON encoded
        $this->assertEquals(json_encode($details), $this->mock_audit_log_data['details'], "Details should be JSON encoded");
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
        
        // Perform the logging with pre-encoded JSON
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Verify the log was created
        $this->assertInstanceOf(AuditLog::class, $result, "Audit logger should return an AuditLog instance");
        
        // Verify the details were not double-encoded
        $this->assertEquals($details, $this->mock_audit_log_data['details'], "Pre-encoded JSON details should not be double-encoded");
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
        
        // Perform the logging with a specific user ID
        $result = AuditLogger::log($action, $entityType, $entityId, $details, $specificUserId);
        
        // Verify the log was created with the specific user ID
        $this->assertInstanceOf(AuditLog::class, $result, "Audit logger should return an AuditLog instance");
        $this->assertEquals($specificUserId, $this->mock_audit_log_data['user_id'], "User ID should match the specified ID, not the current user");
    }
    
    /**
     * Test logging failure with invalid entity ID
     */
    public function testLoggingWithInvalidEntityId()
    {
        // Mock AuditLog::create to return false (simulating a failure)
        Functions\when('AuditLog::create')->justReturn(false);
        
        $action = 'delete_patient';
        $entityType = 'patient';
        $entityId = 'invalid'; // Not a numeric ID
        $details = ['reason' => 'duplicate record'];
        
        // Perform the logging
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Should return false on failure
        $this->assertFalse($result, "Audit logger should return false when creation fails");
    }
    
    /**
     * Test logging with non-serializable details
     */
    public function testLoggingWithNonSerializableDetails()
    {
        $action = 'test_action';
        $entityType = 'test';
        $entityId = A999;
        
        // Create a circular reference that can't be JSON encoded
        $details = [];
        $details['self'] = &$details;
        
        // This should not throw an exception but should return false
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Should handle the error gracefully
        $this->assertFalse($result, "Audit logger should handle non-serializable details gracefully");
    }
    
    /**
     * Test logging when no user is logged in
     */
    public function testLoggingWithNoUser()
    {
        // Mock get_current_user_id to return 0 (no user)
        Functions\when('get_current_user_id')->justReturn(0);
        
        $action = 'system_action';
        $entityType = 'system';
        $entityId = 1;
        $details = ['event' => 'scheduled_maintenance'];
        
        // Perform the logging
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Should still create the log with user_id = 0
        $this->assertInstanceOf(AuditLog::class, $result, "Audit logger should create logs even when no user is logged in");
        $this->assertEquals(0, $this->mock_audit_log_data['user_id'], "User ID should be 0 when no user is logged in");
    }
    
    /**
     * Test proper sanitization of audit log details
     */
    public function testDetailsSanitization()
    {
        $action = 'login_attempt';
        $entityType = 'user';
        $entityId = 999;
        
        // Include sensitive data that should be sanitized
        $details = [
            'username' => 'testuser',
            'password' => 'sensitive_password', // This should be sanitized
            'credit_card' => '4111-1111-1111-1111', // This should be sanitized
            'success' => false
        ];
        
        // Perform the logging
        $result = AuditLogger::log($action, $entityType, $entityId, $details);
        
        // Verify sensitive data was sanitized
        $this->assertInstanceOf(AuditLog::class, $result, "Audit logger should return an AuditLog instance");
        
        $encoded_details = $this->mock_audit_log_data['details'];
        $decoded_details = json_decode($encoded_details, true);
        
        $this->assertIsArray($decoded_details, "Details should be a valid JSON array");
        $this->assertArrayNotHasKey('password', $decoded_details, "Password should be removed from audit logs");
        $this->assertArrayHasKey('username', $decoded_details, "Non-sensitive data should be preserved");
        $this->assertEquals('testuser', $decoded_details['username'], "Non-sensitive data should be unchanged");
        
        // If credit card is in the logs, it should be masked
        if (isset($decoded_details['credit_card'])) {
            $this->assertNotEquals('4111-1111-1111-1111', $decoded_details['credit_card'], "Credit card numbers should be masked");
        }
    }
}
