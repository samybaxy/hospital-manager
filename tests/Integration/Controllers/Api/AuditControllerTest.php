<?php

namespace HospitalManager\Tests\Integration\Controllers\Api;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\AuditLog;
use HospitalManager\Models\Patient;
use WP_REST_Request;
use WP_REST_Server;

class AuditControllerTest extends TestCase
{
    /**
     * @var \WP_REST_Server
     */
    protected $server;

    /**
     * @var string
     */
    protected $namespace = 'hospital-manager/v1';

    /**
     * @var array
     */
    protected $test_users = [];

    /**
     * @var Patient
     */
    protected $test_patient;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        global $wp_rest_server;
        $this->server = $wp_rest_server = new WP_REST_Server;
        do_action('rest_api_init');
        
        // Create test users with different roles
        $this->test_users['admin'] = $this->createUserWithRole('administrator');
        $this->test_users['doctor'] = $this->createUserWithRole('doctor');
        $this->test_users['patient'] = $this->createUserWithRole('patient');
        
        // Add audit log viewing capability to admin role
        $admin_role = get_role('administrator');
        $admin_role->add_cap('view_audit_log');
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '1234567890',
            'sex' => 'Male'
        ]);
        
        // Create test audit logs
        $this->createTestAuditLogs();
    }

    /**
     * Create test audit logs
     */
    protected function createTestAuditLogs()
    {
        // System-wide audit logs
        for ($i = 0; $i < 5; $i++) {
            $this->createAuditLog([
                'user_id' => $this->test_users['admin'],
                'action' => 'system_config_update',
                'entity_type' => 'system',
                'entity_id' => 0,
                'details' => json_encode(['setting' => "Setting {$i}", 'value' => "Value {$i}"]),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))
            ]);
        }
        
        // Patient-specific audit logs
        $actions = ['patient_created', 'patient_updated', 'visitation_added', 'medication_prescribed'];
        for ($i = 0; $i < 4; $i++) {
            $this->createAuditLog([
                'user_id' => $this->test_users['doctor'],
                'action' => $actions[$i],
                'entity_type' => 'patient',
                'entity_id' => $this->test_patient->id,
                'details' => json_encode(['field' => "Field {$i}", 'value' => "Value {$i}"]),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} hours"))
            ]);
        }
    }

    /**
     * Create a test audit log
     *
     * @param array $data Audit log data
     * @return AuditLog
     */
    protected function createAuditLog($data)
    {
        return AuditLog::create($data);
    }

    /**
     * Test getting all audit logs as admin
     */
    public function testGetAuditLogsAsAdmin()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create request to get audit logs
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check response data structure
        $data = $response->get_data();
        $this->assertArrayHasKey('data', $data);
        $this->assertArrayHasKey('meta', $data);
        
        // Check that logs are returned
        $this->assertNotEmpty($data['data']);
        
        // Check pagination metadata
        $this->assertArrayHasKey('current_page', $data['meta']);
        $this->assertArrayHasKey('last_page', $data['meta']);
        $this->assertArrayHasKey('per_page', $data['meta']);
        $this->assertArrayHasKey('total', $data['meta']);
        
        // Verify log data contains expected fields and user information
        foreach ($data['data'] as $log) {
            $this->assertObjectHasAttribute('id', $log);
            $this->assertObjectHasAttribute('user_id', $log);
            $this->assertObjectHasAttribute('action', $log);
            $this->assertObjectHasAttribute('entity_type', $log);
            $this->assertObjectHasAttribute('entity_id', $log);
            $this->assertObjectHasAttribute('details', $log);
            $this->assertObjectHasAttribute('created_at', $log);
            $this->assertObjectHasAttribute('user_name', $log);
            $this->assertObjectHasAttribute('user_role', $log);
        }
    }
    
    /**
     * Test pagination of audit logs
     */
    public function testAuditLogsPagination()
    {
        // Create additional logs to test pagination
        for ($i = 0; $i < 25; $i++) {
            $this->createAuditLog([
                'user_id' => $this->test_users['admin'],
                'action' => 'test_action',
                'entity_type' => 'test',
                'entity_id' => $i,
                'details' => json_encode(['test' => "value {$i}"]),
                'created_at' => date('Y-m-d H:i:s', strtotime("-{$i} minutes"))
            ]);
        }
        
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create request with pagination parameters
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs");
        $request->set_param('page', 1);
        $request->set_param('per_page', 10);
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check correct number of logs returned
        $data = $response->get_data();
        $this->assertCount(10, $data['data']);
        
        // Check pagination metadata
        $this->assertEquals(1, $data['meta']['current_page']);
        $this->assertEquals(10, $data['meta']['per_page']);
        $this->assertGreaterThan(1, $data['meta']['last_page']);
        $this->assertGreaterThan(10, $data['meta']['total']);
    }
    
    /**
     * Test getting patient-specific audit logs as doctor
     */
    public function testGetPatientLogsAsDoctor()
    {
        // Set current user as doctor
        wp_set_current_user($this->test_users['doctor']);
        
        // Create request to get patient logs
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs/patient/{$this->test_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check that logs are returned
        $logs = $response->get_data();
        $this->assertNotEmpty($logs);
        
        // Check that all logs are for the specific patient
        foreach ($logs as $log) {
            $this->assertEquals('patient', $log->entity_type);
            $this->assertEquals($this->test_patient->id, $log->entity_id);
        }
    }
    
    /**
     * Test getting patient audit logs as admin
     */
    public function testGetPatientLogsAsAdmin()
    {
        // Set current user as admin
        wp_set_current_user($this->test_users['admin']);
        
        // Create request to get patient logs
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs/patient/{$this->test_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status
        $this->assertEquals(200, $response->get_status());
        
        // Check that logs are returned
        $logs = $response->get_data();
        $this->assertNotEmpty($logs);
    }
    
    /**
     * Test unauthorized access to audit logs
     */
    public function testUnauthorizedAccessToAuditLogs()
    {
        // Set current user as patient (who shouldn't have access)
        wp_set_current_user($this->test_users['patient']);
        
        // Try to get all audit logs
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs");
        $response = $this->server->dispatch($request);
        
        // Check response status (should be 403 Forbidden)
        $this->assertEquals(403, $response->get_status());
        
        // Try to get patient audit logs
        $request = new WP_REST_Request('GET', "/{$this->namespace}/audit-logs/patient/{$this->test_patient->id}");
        $response = $this->server->dispatch($request);
        
        // Check response status (should be 403 Forbidden)
        $this->assertEquals(403, $response->get_status());
    }
}
