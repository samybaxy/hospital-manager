<?php

namespace HospitalManager\Services;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Services\NotificationService;
use HospitalManager\Services\WebSocketService;
use HospitalManager\Services\AuditLogger;

class LabResultService
{
    /**
     * Update lab results and send notifications
     */
    public static function updateLabResults($labId, $results, $reportUrl = null)
    {
        $lab = LabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        // Update lab results
        $lab->results = $results;
        $lab->report_url = $reportUrl;
        $lab->status = 'completed';
        $lab->completed_at = current_time('mysql');
        $lab->save();

        // Create notification for patient
        NotificationService::create(
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
        WebSocketService::sendMessage('lab_results', [
            'lab_result_id' => $lab->id,
            'test_type' => $lab->test_type,
            'patient_id' => $lab->patient_id
        ], $lab->patient_id);

        // Also notify the requesting doctor
        if ($lab->requested_by) {
            NotificationService::create(
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

            WebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->id,
                'test_type' => $lab->test_type,
                'patient_id' => $lab->patient_id
            ], $lab->requested_by);
        }

        // Log the action
        AuditLogger::log(
            'update_lab_results',
            'lab_investigation',
            $lab->id,
            [
                'patient_id' => $lab->patient_id,
                'test_type' => $lab->test_type,
                'updated_by' => get_current_user_id()
            ]
        );

        return true;
    }

    /**
     * Request new lab investigation
     */
    public static function requestLabInvestigation($patientId, $testType, $requestedBy, $notes = null)
    {
        $lab = new LabInvestigation([
            'patient_id' => $patientId,
            'test_type' => $testType,
            'requested_by' => $requestedBy,
            'notes' => $notes,
            'status' => 'pending',
            'created_at' => current_time('mysql')
        ]);
        $lab->save();

        // Notify lab technicians
        $labTechs = get_users(['role' => 'lab_tech']);
        foreach ($labTechs as $tech) {
            NotificationService::create(
                $tech->ID,
                'lab_request',
                'New Lab Test Request',
                "New {$testType} test requested for patient #{$patientId}",
                [
                    'lab_id' => $lab->id,
                    'patient_id' => $patientId,
                    'test_type' => $testType
                ]
            );

            WebSocketService::sendMessage('lab_request', [
                'lab_id' => $lab->id,
                'test_type' => $testType,
                'patient_id' => $patientId
            ], $tech->ID);
        }

        // Log the action
        AuditLogger::log(
            'request_lab_investigation',
            'lab_investigation',
            $lab->id,
            [
                'patient_id' => $patientId,
                'test_type' => $testType,
                'requested_by' => $requestedBy
            ]
        );

        return $lab;
    }

    /**
     * Get pending lab investigations for lab technicians
     */
    public static function getPendingInvestigations($page = 1, $perPage = 20)
    {
        $args = array(
            'post_type' => 'lab_investigation',
            'meta_query' => array(
                array(
                    'key' => 'status',
                    'value' => 'pending',
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'ASC',
            'posts_per_page' => $perPage,
            'paged' => $page
        );

        return LabInvestigation::find($args);
    }

    /**
     * Get lab results for a specific patient
     */
    public static function getPatientResults($patientId, $page = 1, $perPage = 20)
    {
        $args = array(
            'post_type' => 'lab_investigation',
            'meta_query' => array(
                array(
                    'key' => 'patient_id',
                    'value' => $patientId,
                    'compare' => '='
                )
            ),
            'orderby' => 'date',
            'order' => 'DESC',
            'posts_per_page' => $perPage,
            'paged' => $page
        );

        return LabInvestigation::find($args);
    }
}
