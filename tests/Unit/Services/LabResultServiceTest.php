<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\LabResultService;
use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Services\NotificationService;
use HospitalManager\Services\WebSocketService;
use Brain\Monkey\Functions;
use Mockery;

class LabResultServiceTest extends TestCase
{
    /**
     * @var Patient Test patient
     */
    protected $test_patient;

    /**
     * @var Doctor Test doctor
     */
    protected $test_doctor;

    /**
     * @var LabInvestigation Mock lab investigation
     */
    protected $test_lab;

    /**
     * @var bool Flag to track if notification was sent
     */
    protected $notification_sent = false;

    /**
     * @var bool Flag to track if websocket message was sent
     */
    protected $websocket_message_sent = false;

    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset our test flags
        $this->notification_sent = false;
        $this->websocket_message_sent = false;
        
        // Create test users
        $patient_user_id = $this->createUserWithRole('patient');
        $doctor_user_id = $this->createUserWithRole('doctor');
        $lab_tech_id = $this->createUserWithRole('lab_tech');
        
        // Create a test patient
        $this->test_patient = $this->createTestPatient([
            'user_id' => $patient_user_id
        ]);
        
        // Create a test doctor
        $this->test_doctor = $this->createTestDoctor([
            'user_id' => $doctor_user_id
        ]);
        
        // Create a test visitation
        $visitation = $this->createTestVisitation([
            'patient_id' => $this->test_patient->id,
            'doctor_id' => $this->test_doctor->id
        ]);
        
        // Create a real test lab investigation
        $this->test_lab = $this->createTestLabInvestigation([
            'visitation_id' => $visitation->id,
            'patient_id' => $this->test_patient->id,
            'requested_by' => $this->test_doctor->id,
            'lab_tech_id' => $lab_tech_id,
            'test_type' => 'Blood Test',
            'status' => 'pending',
            'requested_at' => current_time('mysql'),
            'notes' => 'Test lab investigation'
        ]);
        
        // Mock LabInvestigation::find to return our test instance
        Functions\when('LabInvestigation::find')->alias(function($id) {
            if ($id == $this->test_lab->id) {
                return $this->test_lab;
            }
            return null;
        });
        
        // Mock NotificationService::create in WordPress-MVC style
        Functions\when('apply_filters')->alias(function($tag, $value = '', ...$args) {
            if ($tag === 'pre_notification_create') {
                $this->notification_sent = true;
                return (object)[
                    'id' => 999,
                    'user_id' => $args[0],
                    'type' => $args[1],
                    'title' => $args[2],
                    'message' => $args[3],
                    'data' => $args[4] ?? null
                ];
            }
            return $value;
        });
        
        // Mock WebSocketService::sendMessage
        Functions\when('WebSocketService::sendMessage')->alias(function($channel, $data, $user_id) {
            $this->websocket_message_sent = true;
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
        $result = LabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify success
        $this->assertTrue($result, "Lab result update should return true on success");
        
        // Verify lab was updated
        $this->assertEquals('completed', $this->test_lab->status, "Lab status should be marked as completed, preventing patients from seeing their completed results if not properly set");
        $this->assertEquals($data['results'], $this->test_lab->results, "Lab results were not properly stored, which could lead to incorrect medical information being displayed");
        $this->assertEquals($data['report_url'], $this->test_lab->report_url, "Report URL was not properly stored, making it impossible for patients to access their test results");
        
        // Verify notification was sent
        $this->assertTrue($this->notification_sent, "Notification was not sent to the patient. The patient needs to be notified when their lab results are ready.");
        
        // Verify WebSocket message was sent
        $this->assertTrue($this->websocket_message_sent, "WebSocket message was not sent. Real-time updates are important for immediate patient awareness of results.");
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
        $result = LabResultService::updateLabResults($non_existent_id, $data);
        
        // Verify failure
        $this->assertFalse($result, "Updating a non-existent lab should return false. Allowing updates to non-existent labs could create orphaned data in the system.");
        
        // Verify no notification was sent
        $this->assertFalse($this->notification_sent, "No notification should be sent for non-existent labs");
        
        // Verify no WebSocket message was sent
        $this->assertFalse($this->websocket_message_sent, "No WebSocket message should be sent for non-existent labs");
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
        $result = LabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify success (should still work without report URL)
        $this->assertTrue($result, "Lab result update should succeed even without a report URL. Some lab results don't have associated reports, and the system should handle this case gracefully.");
        
        // Verify lab was updated
        $this->assertEquals('completed', $this->test_lab->status);
        $this->assertEquals($data['results'], $this->test_lab->results);
        
        // Verify notification was still sent
        $this->assertTrue($this->notification_sent, "Notification should still be sent even without a report URL");
        
        // Verify WebSocket message was still sent
        $this->assertTrue($this->websocket_message_sent, "WebSocket message should still be sent even without a report URL");
    }
    
    /**
     * Test validation of minimum required fields
     */
    public function testUpdateLabResultsWithoutRequiredFields()
    {
        // Missing both results and status
        $data = [
            'report_url' => 'https://example.com/reports/lab123.pdf'
        ];
        
        // Test the update method
        $result = LabResultService::updateLabResults($this->test_lab->id, $data);
        
        // Verify failure
        $this->assertFalse($result, "Update should fail when required fields are missing. Results are essential medical data that cannot be omitted.");
        
        // Verify lab status was not changed
        $this->assertEquals('pending', $this->test_lab->status, "Lab status should not change when update fails due to missing required data");
        
        // Verify no notification was sent
        $this->assertFalse($this->notification_sent, "No notification should be sent for failed updates");
    }
}
