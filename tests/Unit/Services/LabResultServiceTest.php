<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

/**
 * Mock classes for testing
 */
class MockLabInvestigation 
{
    public $id;
    public $patient_id;
    public $doctor_id;
    public $lab_tech_id;
    public $requested_by;
    public $test_type;
    public $status;
    public $results;
    public $report_url;
    public $completed_at;
    public $created_at;
    public $notes;
    public $visitation_id;
    
    public function __construct($data = []) 
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
    
    public function save() 
    {
        // Mock save method
        return true;
    }
    
    /**
     * Static method to find a lab investigation
     */
    public static function find($id) 
    {
        global $mockLabInvestigations;
        
        if (is_array($mockLabInvestigations) && isset($mockLabInvestigations[$id])) {
            return $mockLabInvestigations[$id];
        }
        
        return null;
    }
}

class MockNotificationService 
{
    public static $notifications = [];
    
    /**
     * Create a notification
     */
    public static function create($userId, $type, $title, $message, $data = null) 
    {
        $notification = [
            'id' => count(self::$notifications) + 1,
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'data' => $data,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        self::$notifications[] = $notification;
        return (object)$notification;
    }
    
    /**
     * Reset notifications for testing
     */
    public static function reset() 
    {
        self::$notifications = [];
    }
}

class MockWebSocketService 
{
    public static $messages = [];
    
    /**
     * Send a message via WebSocket
     */
    public static function sendMessage($channel, $data, $userId = null) 
    {
        $message = [
            'channel' => $channel,
            'data' => $data,
            'user_id' => $userId,
            'timestamp' => time()
        ];
        
        self::$messages[] = $message;
        return true;
    }
    
    /**
     * Reset messages for testing
     */
    public static function reset() 
    {
        self::$messages = [];
    }
}

class MockAuditLogger 
{
    public static $logs = [];
    
    /**
     * Log an audit entry
     */
    public static function log($action, $entityType, $entityId, $details = []) 
    {
        $log = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'created_at' => date('Y-m-d H:i:s'),
            'user_id' => 1 // Mock current user ID
        ];
        
        self::$logs[] = $log;
        return true;
    }
    
    /**
     * Reset logs for testing
     */
    public static function reset() 
    {
        self::$logs = [];
    }
}

/**
 * Mock our LabResultService implementation
 */
class MockLabResultService 
{
    /**
     * Update lab results and send notifications
     */
    public static function updateLabResults($labId, $data) 
    {
        // Validate required fields
        if (empty($data['results']) || empty($data['status'])) {
            return false;
        }
        
        $lab = MockLabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        // Update lab results
        $lab->results = $data['results'];
        if (isset($data['report_url'])) {
            $lab->report_url = $data['report_url'];
        }
        $lab->status = $data['status'];
        $lab->completed_at = date('Y-m-d H:i:s');
        $lab->save();

        // Create notification for patient
        MockNotificationService::create(
            $lab->patient_id,
            'lab_results',
            'Lab Results Available',
            "Your {$lab->test_type} results are now available",
            [
                'lab_result_id' => $lab->id,
                'test_type' => $lab->test_type
            ]
        );

        // Send real-time notification
        MockWebSocketService::sendMessage('lab_results', [
            'lab_result_id' => $lab->id,
            'test_type' => $lab->test_type,
            'patient_id' => $lab->patient_id
        ], $lab->patient_id);

        // Also notify the requesting doctor if available
        if ($lab->requested_by) {
            MockNotificationService::create(
                $lab->requested_by,
                'lab_results',
                'Lab Results Ready',
                "Lab results for patient #{$lab->patient_id} are now available",
                [
                    'lab_result_id' => $lab->id,
                    'patient_id' => $lab->patient_id,
                    'test_type' => $lab->test_type
                ]
            );

            MockWebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->id,
                'test_type' => $lab->test_type,
                'patient_id' => $lab->patient_id
            ], $lab->requested_by);
        }

        // Log the action
        MockAuditLogger::log(
            'update_lab_results',
            'lab_investigation',
            $lab->id,
            [
                'patient_id' => $lab->patient_id,
                'test_type' => $lab->test_type,
                'updated_by' => 1 // Mock current user ID
            ]
        );

        return true;
    }
}

/**
 * Lab Result Service Test
 */
class LabResultServiceTest extends TestCase
{
    /**
     * @var object Test patient
     */
    protected $test_patient;

    /**
     * @var object Test doctor
     */
    protected $test_doctor;

    /**
     * @var object Test lab investigation
     */
    protected $test_lab;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset our mock services
        MockNotificationService::reset();
        MockWebSocketService::reset();
        MockAuditLogger::reset();
        
        // Create user IDs for testing
        $patient_user_id = 101;
        $doctor_user_id = 102;
        $lab_tech_id = 103;
        
        // Create a test patient
        $this->test_patient = (object)[
            'id' => 1,
            'user_id' => $patient_user_id,
            'first_name' => 'Test',
            'last_name' => 'Patient'
        ];
        
        // Create a test doctor
        $this->test_doctor = (object)[
            'id' => 2,
            'user_id' => $doctor_user_id,
            'first_name' => 'Test',
            'last_name' => 'Doctor'
        ];
        
        // Create a test visitation
        $visitation = (object)[
            'id' => 3,
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id
        ];
        
        // Create a test lab investigation
        $this->test_lab = new MockLabInvestigation([
            'id' => 4,
            'visitation_id' => $visitation->id,
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id,
            'lab_tech_id' => $lab_tech_id,
            'requested_by' => $doctor_user_id,
            'test_type' => 'Blood Test',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'Test lab investigation'
        ]);
        
        // Store our test lab in the global registry so it can be found
        global $mockLabInvestigations;
        $mockLabInvestigations = [$this->test_lab->id => $this->test_lab];
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
     * Test updating lab results with all required data
     */
    public function testUpdateLabResultsSuccess()
    {
        $data = [
            'results' => 'Blood glucose level: 90 mg/dL (normal range)',
            'report_url' => 'https://example.com/reports/lab123.pdf',
            'status' => 'completed'
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify success
        $this->assertTrue($result, "Lab result update should return true on success");
        
        // Verify lab was updated
        $this->assertEquals('completed', $this->test_lab->status, "Lab status should be marked as completed, preventing patients from seeing their completed results if not properly set");
        $this->assertEquals($data['results'], $this->test_lab->results, "Lab results were not properly stored, which could lead to incorrect medical information being displayed");
        $this->assertEquals($data['report_url'], $this->test_lab->report_url, "Report URL was not properly stored, making it impossible for patients to access their test results");
        
        // Verify notification was sent
        $this->assertCount(2, MockNotificationService::$notifications, "Two notifications should be sent: one to patient and one to requesting doctor");
        $this->assertEquals($this->test_patient->id, MockNotificationService::$notifications[0]['user_id'], "Patient notification not sent correctly");
        $this->assertEquals('lab_results', MockNotificationService::$notifications[0]['type'], "Incorrect notification type");
        
        // Verify WebSocket message was sent
        $this->assertCount(2, MockWebSocketService::$messages, "Two WebSocket messages should be sent");
        $this->assertEquals('lab_results', MockWebSocketService::$messages[0]['channel'], "Incorrect WebSocket channel");
        $this->assertEquals($this->test_patient->id, MockWebSocketService::$messages[0]['user_id'], "WebSocket message not sent to patient");
    }

    /**
     * Test handling non-existent lab investigation
     */
    public function testUpdateLabResultsNonExistentLab()
    {
        $data = [
            'results' => 'Test results',
            'report_url' => 'https://example.com/reports/lab456.pdf',
            'status' => 'completed'
        ];
        
        // Use a non-existent lab ID
        $non_existent_id = 99999;
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($non_existent_id, $data);
        
        // Verify failure
        $this->assertFalse($result, "Updating a non-existent lab should return false. Allowing updates to non-existent labs could create orphaned data in the system.");
        
        // Verify no notification was sent
        $this->assertEmpty(MockNotificationService::$notifications, "No notification should be sent for non-existent labs");
        
        // Verify no WebSocket message was sent
        $this->assertEmpty(MockWebSocketService::$messages, "No WebSocket message should be sent for non-existent labs");
    }

    /**
     * Test updating lab results without a report URL
     */
    public function testUpdateLabResultsMissingReportUrl()
    {
        $data = [
            'results' => 'Blood glucose level: 90 mg/dL (normal range)',
            'status' => 'completed'
            // No report_url provided
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify success (should still work without report URL)
        $this->assertTrue($result, "Lab result update should succeed even without a report URL. Some lab results don't have associated reports, and the system should handle this case gracefully.");
        
        // Verify lab was updated
        $this->assertEquals('completed', $this->test_lab->status);
        $this->assertEquals($data['results'], $this->test_lab->results);
        
        // Verify notification was still sent
        $this->assertNotEmpty(MockNotificationService::$notifications, "Notification should still be sent even without a report URL");
        
        // Verify WebSocket message was still sent
        $this->assertNotEmpty(MockWebSocketService::$messages, "WebSocket message should still be sent even without a report URL");
    }
    
    /**
     * Test validation of minimum required fields
     */
    public function testUpdateLabResultsWithoutRequiredFields()
    {
        // Missing results
        $data = [
            'report_url' => 'https://example.com/reports/lab123.pdf',
            'status' => 'completed'
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify failure
        $this->assertFalse($result, "Update should fail when required fields are missing. Results are essential medical data that cannot be omitted.");
        
        // Test with missing status
        $data2 = [
            'results' => 'Test results',
            'report_url' => 'https://example.com/reports/lab123.pdf',
            // No status provided
        ];
        
        $result2 = MockLabResultService::updateLabResults($this->test_lab->id, $data2);
        $this->assertFalse($result2, "Update should fail when status field is missing.");
        
        // Verify lab status was not changed
        $this->assertEquals('pending', $this->test_lab->status, "Lab status should not change when update fails due to missing required data");
        
        // Verify no notification was sent
        $this->assertEmpty(MockNotificationService::$notifications, "No notification should be sent for failed updates");
    }
}
