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
        $lab->completed_at = date('Y-m-d H:i:s');
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
                'lab_result_id' => $lab->id,
                'test_type' => $lab->test_type
            ]
        );

        // Create WebSocket message directly instead of using sendMessage to avoid duplicates
        MockWebSocketService::$messages[] = [
            'id' => uniqid(),
            'channel' => 'lab_results',
            'data' => [
                'lab_result_id' => $lab->id,
                'test_type' => $lab->test_type,
                'patient_id' => $lab->patient_id
            ],
            'timestamp' => time(),
            'user_id' => $patientUserId
        ];

        // Also notify the requesting doctor if available
        if ($lab->doctor_id) {
            MockNotificationService::create(
                $lab->doctor_id,
                'lab_results',
                'Lab Results Ready',
                "Lab results for patient #{$lab->patient_id} are now available",
                [
                    'lab_result_id' => $lab->id,
                    'patient_id' => $lab->patient_id,
                    'test_type' => $lab->test_type
                ]
            );

            // Create WebSocket message directly instead of using sendMessage to avoid duplicates
            MockWebSocketService::$messages[] = [
                'id' => uniqid(),
                'channel' => 'lab_results',
                'data' => [
                    'lab_result_id' => $lab->id,
                    'test_type' => $lab->test_type,
                    'patient_id' => $lab->patient_id
                ],
                'timestamp' => time(),
                'user_id' => $lab->doctor_id
            ];
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
