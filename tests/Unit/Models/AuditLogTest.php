<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\AuditLog;

class AuditLogTest extends TestCase
{
    /**
     * @var int Test user ID
     */
    protected $user_id;
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Create a test user for audit logs
        $this->user_id = $this->createUserWithRole('doctor');
    }
    
    /**
     * Test audit log creation
     */
    public function testCreateAuditLog()
    {
        $data = [
            'user_id' => $this->user_id,
            'action' => 'view_patient',
            'entity_type' => 'patient',
            'entity_id' => 123,
            'details' => json_encode(['page' => 'patient_details']),
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/91.0.4472.124',
            'created_at' => current_time('mysql')
        ];

        $log = AuditLog::create($data);

        $this->assertInstanceOf(AuditLog::class, $log);
        $this->assertEquals($this->user_id, $log->user_id);
        $this->assertEquals('view_patient', $log->action);
        $this->assertEquals('patient', $log->entity_type);
        $this->assertEquals(123, $log->entity_id);
        $this->assertEquals('192.168.1.1', $log->ip_address);
    }

    /**
     * Test finding an audit log by ID
     */
    public function testFindAuditLog()
    {
        // Create a test audit log
        $log = $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'create_visitation',
            'entity_type' => 'visitation'
        ]);
        
        // Find the audit log by ID
        $found_log = AuditLog::find($log->id);
        
        $this->assertInstanceOf(AuditLog::class, $found_log);
        $this->assertEquals($log->id, $found_log->id);
        $this->assertEquals($log->user_id, $found_log->user_id);
        $this->assertEquals($log->action, $found_log->action);
        $this->assertEquals($log->entity_type, $found_log->entity_type);
    }

    /**
     * Test audit log with complex details
     */
    public function testAuditLogWithComplexDetails()
    {
        $complex_details = [
            'before' => [
                'status' => 'pending',
                'notes' => 'Initial notes'
            ],
            'after' => [
                'status' => 'completed',
                'notes' => 'Updated notes after treatment'
            ],
            'changed_by' => $this->user_id,
            'timestamp' => time()
        ];
        
        $log = $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'update_visitation',
            'entity_type' => 'visitation',
            'entity_id' => 456,
            'details' => json_encode($complex_details)
        ]);
        
        $found_log = AuditLog::find($log->id);
        $retrieved_details = json_decode($found_log->details, true);
        
        $this->assertEquals($complex_details['before']['status'], $retrieved_details['before']['status']);
        $this->assertEquals($complex_details['after']['notes'], $retrieved_details['after']['notes']);
        $this->assertEquals($complex_details['changed_by'], $retrieved_details['changed_by']);
    }

    /**
     * Test getting user activity logs
     */
    public function testGetUserActivityLogs()
    {
        // Create multiple test logs for the same user
        for ($i = 0; $i < 3; $i++) {
            $this->createTestAuditLog([
                'user_id' => $this->user_id,
                'action' => 'action_' . $i,
                'entity_type' => 'test_entity'
            ]);
        }
        
        // Get logs for this user
        $logs = AuditLog::where('user_id', $this->user_id);
        
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertEquals($this->user_id, $log->user_id);
        }
    }

    /**
     * Test filtering logs by action
     */
    public function testFilterLogsByAction()
    {
        // Create a specific action log
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'login',
            'entity_type' => 'user'
        ]);
        
        // Get logs with this action
        $logs = AuditLog::where('action', 'login');
        
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertEquals('login', $log->action);
        }
    }

    /**
     * Test getting logs within a date range
     */
    public function testGetLogsWithinDateRange()
    {
        // Create logs with different dates
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $today = current_time('mysql');
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'past_action',
            'created_at' => $yesterday
        ]);
        
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'current_action',
            'created_at' => $today
        ]);
        
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'future_action',
            'created_at' => $tomorrow
        ]);
        
        // Get logs for today only
        $today_date = date('Y-m-d');
        $logs = AuditLog::whereRaw("DATE(created_at) = '$today_date'");
        
        $this->assertNotEmpty($logs);
        foreach ($logs as $log) {
            $this->assertEquals('current_action', $log->action);
        }
    }

    /**
     * Test finding logs for a specific entity
     */
    public function testFindLogsForEntity()
    {
        $entity_id = 789;
        
        // Create logs for this entity
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'view_entity',
            'entity_type' => 'test_entity',
            'entity_id' => $entity_id
        ]);
        
        $this->createTestAuditLog([
            'user_id' => $this->user_id,
            'action' => 'edit_entity',
            'entity_type' => 'test_entity',
            'entity_id' => $entity_id
        ]);
        
        // Get logs for this entity
        $logs = AuditLog::where('entity_id', $entity_id);
        
        $this->assertNotEmpty($logs);
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertEquals($entity_id, $log->entity_id);
            $this->assertEquals('test_entity', $log->entity_type);
        }
    }
}
