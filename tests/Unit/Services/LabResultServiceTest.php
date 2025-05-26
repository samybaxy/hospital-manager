<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use HospitalManager\Tests\Mocks\Services\MockAuditLogger;
use HospitalManager\Tests\Mocks\Services\MockWebSocketService;
use HospitalManager\Tests\Mocks\Services\MockNotificationService;
use HospitalManager\Tests\Mocks\Services\MockNotification;
use HospitalManager\Tests\Mocks\Services\MockUser;
use HospitalManager\Tests\Mocks\Services\MockLabInvestigation;
use HospitalManager\Tests\Mocks\Services\MockLabResultService;
use Mockery;

/**
 * Import our centralized mock classes from the Mocks directory
 * For using the centralized patterns to avoid duplication
 */

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
        MockAuditLogger::reset();
        
        // Ensure WebSocketService is properly reset
        MockWebSocketService::$messages = [];
        MockWebSocketService::$transient_storage = [];
        
        // Create user IDs for testing
        $patient_user_id = 101;
        $doctor_user_id = 102;
        $lab_tech_id = 103;
        
        // Add test users to the MockUser system
        MockUser::addUser($patient_user_id, [
            'display_name' => 'Test Patient',
            'user_email' => 'patient@example.com'
        ]);
        
        MockUser::addUser($doctor_user_id, [
            'display_name' => 'Test Doctor',
            'user_email' => 'doctor@example.com'
        ]);
        
        MockUser::addUser($lab_tech_id, [
            'display_name' => 'Lab Technician',
            'user_email' => 'labtech@example.com'
        ]);
        
        // Create a test patient
        $this->test_patient = (object)[
            'ID' => 1,
            'user_id' => $patient_user_id,
            'first_name' => 'Test',
            'last_name' => 'Patient'
        ];
        
        // Store reference to the test patient globally so the mock service can access it
        global $testPatient;
        $testPatient = $this->test_patient;
        
        // Create a test doctor
        $this->test_doctor = (object)[
            'ID' => 2,
            'user_id' => $doctor_user_id,
            'first_name' => 'Test',
            'last_name' => 'Doctor'
        ];
        
        // Create a test visitation
        $visitation = (object)[
            'ID' => 3,
            'patient_id' => $this->test_patient->ID,
            'doctor_id' => $this->test_doctor->ID
        ];
        
        // Create a test lab investigation
        $this->test_lab = new MockLabInvestigation([
            'ID' => 4,
            'visitation_id' => $visitation->ID,
            'patient_id' => $this->test_patient->ID,
            'doctor_id' => $this->test_doctor->ID,
            'lab_tech_id' => $lab_tech_id,
            'test_type' => 'Blood Test',
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => 'Test lab investigation'
        ]);
        
        // Store our test lab in the global registry so it can be found
        global $mockLabInvestigations;
        $mockLabInvestigations = [$this->test_lab->ID => $this->test_lab];
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
        // Make sure previous messages are cleared
        MockNotificationService::reset();
        MockWebSocketService::reset();
        MockWebSocketService::$messages = [];
        
        $data = [
            'results' => 'Blood glucose level: 90 mg/dL (normal range)',
            'status' => 'completed'
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->ID, $data);
        
        // Verify success
        $this->assertTrue($result, "Lab result update should return true on success");
        
        // Verify lab was updated
        $this->assertEquals('completed', $this->test_lab->status, "Lab status should be marked as completed, preventing patients from seeing their completed results if not properly set");
        $this->assertEquals($data['results'], $this->test_lab->results, "Lab results were not properly stored, which could lead to incorrect medical information being displayed");
        
        // Verify notification was sent
        $this->assertCount(2, MockNotificationService::$notifications, "Two notifications should be sent: one to patient and one to requesting doctor");
        // The patient ID needs to correspond to user ID in our test setup (patient_id = 1, user_id = 101)
        $this->assertEquals($this->test_patient->user_id, MockNotificationService::$notifications[0]['user_id'], "Patient notification not sent correctly");
        $this->assertEquals('lab_results', MockNotificationService::$notifications[0]['type'], "Incorrect notification type");
        
        // Verify WebSocket messages were sent - instead of count, we'll check that messages exist
        $this->assertNotEmpty(MockWebSocketService::$messages, "WebSocket messages should be sent");
        
        // Find the message for the patient
        $patientMessage = null;
        foreach (MockWebSocketService::$messages as $message) {
            if ($message['user_id'] === $this->test_patient->user_id && $message['channel'] === 'lab_results') {
                $patientMessage = $message;
                break;
            }
        }
        
        // Verify patient message
        $this->assertNotNull($patientMessage, "WebSocket message for patient not found");
        $this->assertEquals('lab_results', $patientMessage['channel'], "Incorrect WebSocket channel for patient");
    }

    /**
     * Test handling non-existent lab investigation
     */
    public function testUpdateLabResultsNonExistentLab()
    {
        $data = [
            'results' => 'Test results',
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
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->ID, $data);
        
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
            'status' => 'completed'
        ];
        
        // Test the update method
        $result = MockLabResultService::updateLabResults($this->test_lab->ID, $data);
        
        // Verify failure
        $this->assertFalse($result, "Update should fail when required fields are missing. Results are essential medical data that cannot be omitted.");
        
        // Test with missing status
        $data2 = [
            'results' => 'Test results',
            // No status provided
        ];
        
        $result2 = MockLabResultService::updateLabResults($this->test_lab->ID, $data2);
        $this->assertFalse($result2, "Update should fail when status field is missing.");
        
        // Verify lab status was not changed
        $this->assertEquals('pending', $this->test_lab->status, "Lab status should not change when update fails due to missing required data");
        
        // Verify no notification was sent
        $this->assertEmpty(MockNotificationService::$notifications, "No notification should be sent for failed updates");
    }
}
