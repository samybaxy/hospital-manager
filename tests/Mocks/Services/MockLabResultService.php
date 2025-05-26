<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Centralized Mock LabResultService for testing
 * 
 * This class provides a unified mock implementation of the LabResultService
 * that can be used across multiple test cases.
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
        $lab->status = $data['status'];
        $lab->save();

        // Get test patient for this test only to get the user_id from patient_id
        // In a real application, there would be a proper patient-to-user mapping
        global $testPatient;
        $patientUserId = isset($testPatient) && isset($testPatient->user_id) ? $testPatient->user_id : $lab->patient_id;
        
        // Clear all previous WebSocket messages to ensure we start fresh
        MockWebSocketService::$messages = [];
        
        // Create notification for patient
        MockNotificationService::create(
            $patientUserId, // Use the user ID, not patient ID
            'lab_results',
            'Lab Results Available',
            "Your {$lab->test_type} results are now available",
            [
                'lab_result_id' => $lab->ID,
                'test_type' => $lab->test_type
            ]
        );

        // Create WebSocket message directly instead of using sendMessage to avoid duplicates
        MockWebSocketService::$messages[] = [
            'ID' => uniqid(),
            'channel' => 'lab_results',
            'data' => [
                'lab_result_id' => $lab->ID,
                'test_type' => $lab->test_type,
                'patient_id' => $lab->patient_id
            ],
            'timestamp' => time(),
            'user_id' => $patientUserId
        ];

        // Also notify the requesting doctor if available
        if ($lab->doctor_id) {
            // Get the doctor's user_id from our test system
            // In real code, you'd have a method to lookup the user_id from doctor_id
            $doctorUserId = ($lab->doctor_id == 2) ? 102 : $lab->doctor_id;
            
            MockNotificationService::create(
                $doctorUserId,  // Use doctor's user_id instead of doctor_id
                'lab_results',
                'Lab Results Ready',
                "Lab results for patient #{$lab->patient_id} are now available",
                [
                    'lab_result_id' => $lab->ID,
                    'patient_id' => $lab->patient_id,
                    'test_type' => $lab->test_type
                ]
            );

            // Create WebSocket message directly instead of using sendMessage to avoid duplicates
            MockWebSocketService::$messages[] = [
                'ID' => uniqid(),
                'channel' => 'lab_results',
                'data' => [
                    'lab_result_id' => $lab->ID,
                    'test_type' => $lab->test_type,
                    'patient_id' => $lab->patient_id
                ],
                'timestamp' => time(),
                'user_id' => $doctorUserId  // Use the doctorUserId we determined above
            ];
        }

        // Log the action
        MockAuditLogger::log(
            'update_lab_results',
            'lab_investigation',
            $lab->ID,
            [
                'patient_id' => $lab->patient_id,
                'test_type' => $lab->test_type,
                'updated_by' => 1 // Mock current user ID
            ]
        );

        return true;
    }
}
