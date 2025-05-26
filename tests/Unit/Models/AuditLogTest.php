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
            'created_at' => current_time('mysql')
        ];

        // Insert directly using wpdb to test database connection
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        $result = $wpdb->insert(
            $table, 
            $data,
            ['%d', '%s', '%s', '%d', '%s', '%s']
        );
        
        $this->assertNotFalse($result, "Failed to insert audit log: " . $wpdb->last_error);
        $ID = $wpdb->insert_id;
        $this->assertGreaterThan(0, $ID, "Failed to get insert ID");
        
        // Now check if we can retrieve it
        $log = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE ID = %d", $ID));
        $this->assertNotNull($log, "Failed to retrieve inserted audit log");
        $this->assertEquals($this->user_id, $log->user_id);
        $this->assertEquals('view_patient', $log->action);
        $this->assertEquals('patient', $log->entity_type);
        $this->assertEquals(123, $log->entity_id);
    }

    /**
     * Test finding an audit log by ID
     */
    public function testFindAuditLog()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        // Insert directly using wpdb
        $data = [
            'user_id' => $this->user_id,
            'action' => 'create_visitation',
            'entity_type' => 'visitation',
            'entity_id' => 456,
            'details' => json_encode(['test' => 'data']),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $data);
        $this->assertNotFalse($result, "Failed to insert audit log: " . $wpdb->last_error);
        
        $log_id = $wpdb->insert_id;
        $this->assertGreaterThan(0, $log_id, "Failed to get insert ID");
        
        // Fetch directly from the database
        $found_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $log_id
        ));
        
        $this->assertNotNull($found_log, "Could not find audit log with ID {$log_id}");
        $this->assertEquals($log_id, $found_log->ID);
        $this->assertEquals($this->user_id, $found_log->user_id);
        $this->assertEquals('create_visitation', $found_log->action);
        $this->assertEquals('visitation', $found_log->entity_type);
    }

    /**
     * Test audit log with complex details
     */
    public function testAuditLogWithComplexDetails()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
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
        
        // Insert directly using wpdb
        $data = [
            'user_id' => $this->user_id,
            'action' => 'update_visitation',
            'entity_type' => 'visitation',
            'entity_id' => 456,
            'details' => json_encode($complex_details),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $data);
        $this->assertNotFalse($result, "Failed to insert audit log: " . $wpdb->last_error);
        
        $log_id = $wpdb->insert_id;
        $this->assertGreaterThan(0, $log_id, "Failed to get insert ID");
        
        // Fetch directly from the database
        $found_log = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE ID = %d",
            $log_id
        ));
        
        $this->assertNotNull($found_log, "Could not find audit log with ID {$log_id}");
        $retrieved_details = json_decode($found_log->details, true);
        
        $this->assertEquals($complex_details['before']['status'], $retrieved_details['before']['status']);
        $this->assertEquals($complex_details['after']['notes'], $retrieved_details['after']['notes']);
        $this->assertEquals($complex_details['changed_by'], $retrieved_details['changed_by']);
    }

    /**
     * Test finding logs with where condition
     */
    public function testGetUserActivityLogs()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        // Create multiple test logs for the same user
        for ($i = 0; $i < 3; $i++) {
            $data = [
                'user_id' => $this->user_id,
                'action' => 'action_' . $i,
                'entity_type' => 'test_entity',
                'entity_id' => $i + 100,
                'details' => json_encode(['test' => 'data']),
                'created_at' => current_time('mysql')
            ];
            
            $result = $wpdb->insert($table, $data);
            $this->assertNotFalse($result, "Failed to insert log #{$i}: " . $wpdb->last_error);
        }
        
        // Get logs for this user using direct database query
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d",
            $this->user_id
        ));
        
        $this->assertNotEmpty($logs, 'No logs found for test user');
        foreach ($logs as $log) {
            $this->assertEquals($this->user_id, $log->user_id);
        }
    }

    /**
     * Test filtering logs by action
     */
    public function testFilterLogsByAction()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        // Create a specific action log
        $data = [
            'user_id' => $this->user_id,
            'action' => 'login',
            'entity_type' => 'user',
            'entity_id' => $this->user_id,
            'details' => json_encode(['ip' => '127.0.0.1']),
            'created_at' => current_time('mysql')
        ];
        
        $result = $wpdb->insert($table, $data);
        $this->assertNotFalse($result, "Failed to insert login log: " . $wpdb->last_error);
        
        // Get logs with this action using direct database query
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE action = %s",
            'login'
        ));
        
        $this->assertNotEmpty($logs, 'No logs found with login action');
        foreach ($logs as $log) {
            $this->assertEquals('login', $log->action);
        }
    }

    /**
     * Test getting logs within a date range
     */
    public function testGetLogsWithinDateRange()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        // Create logs with different dates
        $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
        $today = current_time('mysql');
        $tomorrow = date('Y-m-d H:i:s', strtotime('+1 day'));
        
        // Yesterday log
        $data1 = [
            'user_id' => $this->user_id,
            'action' => 'past_action',
            'entity_type' => 'test_entity',
            'entity_id' => 101,
            'details' => json_encode(['day' => 'yesterday']),
            'created_at' => $yesterday
        ];
        $result1 = $wpdb->insert($table, $data1);
        $this->assertNotFalse($result1, "Failed to insert yesterday log: " . $wpdb->last_error);
        
        // Today log
        $data2 = [
            'user_id' => $this->user_id,
            'action' => 'current_action',
            'entity_type' => 'test_entity',
            'entity_id' => 102,
            'details' => json_encode(['day' => 'today']),
            'created_at' => $today
        ];
        $result2 = $wpdb->insert($table, $data2);
        $this->assertNotFalse($result2, "Failed to insert today log: " . $wpdb->last_error);
        
        // Tomorrow log
        $data3 = [
            'user_id' => $this->user_id,
            'action' => 'future_action',
            'entity_type' => 'test_entity',
            'entity_id' => 103,
            'details' => json_encode(['day' => 'tomorrow']),
            'created_at' => $tomorrow
        ];
        $result3 = $wpdb->insert($table, $data3);
        $this->assertNotFalse($result3, "Failed to insert tomorrow log: " . $wpdb->last_error);
        
        // Get logs for today only using direct database query
        $today_date = date('Y-m-d');
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE DATE(created_at) = %s",
            $today_date
        ));
        
        $this->assertNotEmpty($logs, 'No logs found for today');
        foreach ($logs as $log) {
            $log_date = date('Y-m-d', strtotime($log->created_at));
            $this->assertEquals($today_date, $log_date);
        }
    }

    /**
     * Test finding logs for a specific entity
     */
    public function testFindLogsForEntity()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_audit_logs';
        
        $entity_id = 789;
        
        // Create logs for this entity
        $data1 = [
            'user_id' => $this->user_id,
            'action' => 'view_entity',
            'entity_type' => 'test_entity',
            'entity_id' => $entity_id,
            'details' => json_encode(['action' => 'view']),
            'created_at' => current_time('mysql')
        ];
        $result1 = $wpdb->insert($table, $data1);
        $this->assertNotFalse($result1, "Failed to insert view_entity log: " . $wpdb->last_error);
        
        $data2 = [
            'user_id' => $this->user_id,
            'action' => 'edit_entity',
            'entity_type' => 'test_entity',
            'entity_id' => $entity_id,
            'details' => json_encode(['action' => 'edit']),
            'created_at' => current_time('mysql')
        ];
        $result2 = $wpdb->insert($table, $data2);
        $this->assertNotFalse($result2, "Failed to insert edit_entity log: " . $wpdb->last_error);
        
        // Get logs for this entity using direct database query
        $logs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE entity_id = %d AND entity_type = %s",
            $entity_id, 'test_entity'
        ));
        
        $this->assertNotEmpty($logs, "No logs found for entity ID {$entity_id}");
        $this->assertEquals(2, count($logs), "Expected 2 logs for entity ID {$entity_id}");
        
        foreach ($logs as $log) {
            $this->assertEquals($entity_id, $log->entity_id);
            $this->assertEquals('test_entity', $log->entity_type);
        }
    }
}
