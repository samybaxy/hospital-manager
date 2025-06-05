<?php

namespace HospitalManager\Services;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Services\NotificationService;
use HospitalManager\Services\WebSocketService;
use HospitalManager\Services\AuditLogger;

class LabResultService
{
    /**
     * Update lab results and send notifications
     */
    public static function updateLabResults($labId, $results, $flags = null, $lab_notes = null)
    {
        $lab = LabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        // Update lab results using the model method
        $success = $lab->updateResults($results, $flags, $lab_notes);
        
        if (!$success) {
            return false;
        }

        // Get patient and doctor info for notifications
        $patient = Patient::find($lab->attributes['patient_id']);
        $doctor = Doctor::find($lab->attributes['doctor_id']);

        // Create notification for patient
        if ($patient) {
            NotificationService::create(
                $patient->user_id ?? $patient->ID,
                'lab_results',
                'Lab Results Available',
                "Your lab test results are now available",
                [
                    'lab_result_id' => $lab->attributes['ID'],
                    'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                    'is_critical' => $lab->attributes['is_critical'] ?? 0,
                    'is_abnormal' => $lab->attributes['is_abnormal'] ?? 0
                ]
            );

            // Send real-time notification
            WebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->attributes['ID'],
                'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                'patient_id' => $lab->attributes['patient_id'],
                'is_critical' => $lab->attributes['is_critical'] ?? 0,
                'is_abnormal' => $lab->attributes['is_abnormal'] ?? 0
            ], $patient->user_id ?? $patient->ID);
        }

        // Also notify the requesting doctor
        if ($doctor && $lab->attributes['doctor_id']) {
            $message = "Lab results for patient are now available";
            if ($lab->attributes['is_critical']) {
                $message = "CRITICAL: Lab results for patient require immediate attention";
            } elseif ($lab->attributes['is_abnormal']) {
                $message = "ABNORMAL: Lab results for patient are outside normal range";
            }

            NotificationService::create(
                $doctor->user_id ?? $doctor->ID,
                'lab_results',
                $lab->attributes['is_critical'] ? 'CRITICAL Lab Results' : 'Lab Results Ready',
                $message,
                [
                    'lab_result_id' => $lab->attributes['ID'],
                    'patient_id' => $lab->attributes['patient_id'],
                    'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                    'is_critical' => $lab->attributes['is_critical'] ?? 0,
                    'is_abnormal' => $lab->attributes['is_abnormal'] ?? 0
                ]
            );

            WebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->attributes['ID'],
                'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                'patient_id' => $lab->attributes['patient_id'],
                'is_critical' => $lab->attributes['is_critical'] ?? 0,
                'is_abnormal' => $lab->attributes['is_abnormal'] ?? 0
            ], $doctor->user_id ?? $doctor->ID);
        }

        // Log the action
        AuditLogger::log(
            'update_lab_results',
            'lab_investigation',
            $lab->attributes['ID'],
            [
                'patient_id' => $lab->attributes['patient_id'],
                'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                'is_critical' => $lab->attributes['is_critical'] ?? 0,
                'is_abnormal' => $lab->attributes['is_abnormal'] ?? 0,
                'updated_by' => get_current_user_id()
            ]
        );

        return true;
    }

    /**
     * Request new lab investigation
     */
    public static function requestLabInvestigation($data)
    {
        $lab = LabInvestigation::create($data);
        
        if (!$lab) {
            return false;
        }

        // Notify the assigned lab technician
        if (isset($data['lab_tech_id'])) {
            NotificationService::create(
                $data['lab_tech_id'],
                'lab_request',
                'New Lab Test Request',
                "New lab test requested for patient",
                [
                    'lab_id' => $lab->attributes['ID'],
                    'patient_id' => $data['patient_id'],
                    'sample_type' => $data['sample_type'] ?? 'Lab Test',
                    'visitation_id' => $data['visitation_id'] ?? null
                ]
            );

            WebSocketService::sendMessage('lab_request', [
                'lab_id' => $lab->attributes['ID'],
                'sample_type' => $data['sample_type'] ?? 'Lab Test',
                'patient_id' => $data['patient_id'],
                'visitation_id' => $data['visitation_id'] ?? null
            ], $data['lab_tech_id']);
        }

        // Also notify other lab technicians if no specific tech assigned
        if (!isset($data['lab_tech_id'])) {
            $labTechs = get_users(['role' => 'lab_tech']);
            foreach ($labTechs as $tech) {
                NotificationService::create(
                    $tech->ID,
                    'lab_request',
                    'New Lab Test Request',
                    "New lab test request needs assignment",
                    [
                        'lab_id' => $lab->attributes['ID'],
                        'patient_id' => $data['patient_id'],
                        'sample_type' => $data['sample_type'] ?? 'Lab Test'
                    ]
                );

                WebSocketService::sendMessage('lab_request', [
                    'lab_id' => $lab->attributes['ID'],
                    'sample_type' => $data['sample_type'] ?? 'Lab Test',
                    'patient_id' => $data['patient_id']
                ], $tech->ID);
            }
        }

        // Log the action
        AuditLogger::log(
            'request_lab_investigation',
            'lab_investigation',
            $lab->attributes['ID'],
            [
                'patient_id' => $data['patient_id'],
                'sample_type' => $data['sample_type'] ?? 'Lab Test',
                'doctor_id' => $data['doctor_id'] ?? null,
                'lab_tech_id' => $data['lab_tech_id'] ?? null
            ]
        );

        return $lab;
    }

    /**
     * Notify about lab request
     */
    public static function notifyLabRequest($labId)
    {
        $lab = LabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        // Notify the lab tech
        if ($lab->attributes['lab_tech_id']) {
            NotificationService::create(
                $lab->attributes['lab_tech_id'],
                'lab_request',
                'New Lab Test Assignment',
                "You have been assigned a new lab test",
                [
                    'lab_id' => $lab->attributes['ID'],
                    'patient_id' => $lab->attributes['patient_id'],
                    'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test'
                ]
            );

            WebSocketService::sendMessage('lab_assignment', [
                'lab_id' => $lab->attributes['ID'],
                'sample_type' => $lab->attributes['sample_type'] ?? 'Lab Test',
                'patient_id' => $lab->attributes['patient_id']
            ], $lab->attributes['lab_tech_id']);
        }

        return true;
    }

    /**
     * Notify about results ready
     */
    public static function notifyResultsReady($labId)
    {
        return self::updateLabResults($labId, null);
    }

    /**
     * Get pending lab investigations for lab technicians
     */
    public static function getPendingInvestigations($techId = null, $page = 1, $perPage = 20)
    {
        return LabInvestigation::getPendingForTech($techId, $perPage);
    }

    /**
     * Get lab results for a specific patient
     */
    public static function getPatientResults($patientId, $page = 1, $perPage = 20)
    {
        return LabInvestigation::getForPatient($patientId, $perPage);
    }

    /**
     * Get dashboard statistics for lab
     */
    public static function getDashboardStats($techId = null)
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_lab_investigations';
        
        $where_tech = $techId ? $wpdb->prepare(" AND lab_tech_id = %d", $techId) : "";
        
        $stats = [
            'pending' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status IN ('requested', 'sample_collected') {$where_tech}"),
            'in_progress' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE status = 'in_progress' {$where_tech}"),
            'completed_today' => $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$table} WHERE status = 'completed' AND DATE(updated_at) = %s {$where_tech}", current_time('Y-m-d'))),
            'critical_results' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_critical = 1 AND status = 'completed' {$where_tech}"),
            'abnormal_results' => $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE is_abnormal = 1 AND status = 'completed' {$where_tech}")
        ];
        
        return $stats;
    }

    /**
     * Update investigation status
     */
    public static function updateStatus($labId, $status)
    {
        $lab = LabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        $success = $lab->updateStatus($status);
        
        if ($success) {
            // Log status change
            AuditLogger::log(
                'update_lab_status',
                'lab_investigation',
                $lab->attributes['ID'],
                [
                    'old_status' => $lab->attributes['status'] ?? 'unknown',
                    'new_status' => $status,
                    'patient_id' => $lab->attributes['patient_id'],
                    'updated_by' => get_current_user_id()
                ]
            );
        }
        
        return $success;
    }
}
