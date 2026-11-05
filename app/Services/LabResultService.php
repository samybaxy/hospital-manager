<?php

namespace HospitalManager\Services;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Patient;
use HospitalManager\Models\Doctor;
use HospitalManager\Services\NotificationService;
use HospitalManager\Services\WebSocketService;
use HospitalManager\Services\AuditLogger;

class LabResultService extends BaseService
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

        self::sendResultNotifications($lab);

        return true;
    }

    /**
     * Notify the patient and requesting doctor that results are ready, and audit it.
     * Does not modify the investigation.
     *
     * @param LabInvestigation $lab
     */
    private static function sendResultNotifications($lab)
    {
        // Get patient and doctor info for notifications
        $patient = Patient::find($lab->getAttributes()['patient_id']);
        $doctor = Doctor::find($lab->getAttributes()['doctor_id']);

        // Notifications go to WordPress users. Never fall back to the patient/doctor
        // *record* ID: that would notify whichever user happens to have that ID.
        $patient_user_id = $patient ? (int) $patient->user_id : 0;
        $doctor_user_id = $doctor ? (int) $doctor->user_id : 0;

        // Create notification for patient (only if they have a portal account)
        if ($patient_user_id > 0) {
            NotificationService::create(
                $patient_user_id,
                'lab_results',
                'Lab Results Available',
                "Your lab test results are now available",
                [
                    'lab_result_id' => $lab->getAttributes()['ID'],
                    'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                    'is_critical' => $lab->getAttributes()['is_critical'] ?? 0,
                    'is_abnormal' => $lab->getAttributes()['is_abnormal'] ?? 0
                ]
            );

            // Send real-time notification
            WebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->getAttributes()['ID'],
                'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                'patient_id' => $lab->getAttributes()['patient_id'],
                'is_critical' => $lab->getAttributes()['is_critical'] ?? 0,
                'is_abnormal' => $lab->getAttributes()['is_abnormal'] ?? 0
            ], $patient_user_id, false); // notification already created above
        }

        // Also notify the requesting doctor
        if ($doctor_user_id > 0) {
            $message = "Lab results for patient are now available";
            if ($lab->getAttributes()['is_critical']) {
                $message = "CRITICAL: Lab results for patient require immediate attention";
            } elseif ($lab->getAttributes()['is_abnormal']) {
                $message = "ABNORMAL: Lab results for patient are outside normal range";
            }

            NotificationService::create(
                $doctor_user_id,
                'lab_results',
                $lab->getAttributes()['is_critical'] ? 'CRITICAL Lab Results' : 'Lab Results Ready',
                $message,
                [
                    'lab_result_id' => $lab->getAttributes()['ID'],
                    'patient_id' => $lab->getAttributes()['patient_id'],
                    'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                    'is_critical' => $lab->getAttributes()['is_critical'] ?? 0,
                    'is_abnormal' => $lab->getAttributes()['is_abnormal'] ?? 0
                ]
            );

            WebSocketService::sendMessage('lab_results', [
                'lab_result_id' => $lab->getAttributes()['ID'],
                'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                'patient_id' => $lab->getAttributes()['patient_id'],
                'is_critical' => $lab->getAttributes()['is_critical'] ?? 0,
                'is_abnormal' => $lab->getAttributes()['is_abnormal'] ?? 0
            ], $doctor_user_id, false); // notification already created above
        }

        // Log the action
        AuditLogger::log(
            'update_lab_results',
            'lab_investigation',
            $lab->getAttributes()['ID'],
            [
                'patient_id' => $lab->getAttributes()['patient_id'],
                'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                'is_critical' => $lab->getAttributes()['is_critical'] ?? 0,
                'is_abnormal' => $lab->getAttributes()['is_abnormal'] ?? 0,
                'updated_by' => get_current_user_id()
            ]
        );
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
                    'lab_id' => $lab->getAttributes()['ID'],
                    'patient_id' => $data['patient_id'],
                    'sample_type' => $data['sample_type'] ?? 'Lab Test',
                    'visitation_id' => $data['visitation_id'] ?? null
                ]
            );

            WebSocketService::sendMessage('lab_request', [
                'lab_id' => $lab->getAttributes()['ID'],
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
                        'lab_id' => $lab->getAttributes()['ID'],
                        'patient_id' => $data['patient_id'],
                        'sample_type' => $data['sample_type'] ?? 'Lab Test'
                    ]
                );

                WebSocketService::sendMessage('lab_request', [
                    'lab_id' => $lab->getAttributes()['ID'],
                    'sample_type' => $data['sample_type'] ?? 'Lab Test',
                    'patient_id' => $data['patient_id']
                ], $tech->ID);
            }
        }

        // Log the action
        AuditLogger::log(
            'request_lab_investigation',
            'lab_investigation',
            $lab->getAttributes()['ID'],
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
        try {
            $lab = LabInvestigation::find($labId);
            if (!$lab) {
                error_log("LabResultService::notifyLabRequest - Lab investigation not found with ID: " . $labId);
                return false;
            }
            
            // `attributes` is protected: isset($lab->attributes) from here was always
            // false, so this method returned early and lab techs were never notified.
            $data = $lab->getAttributes();
            if (empty($data)) {
                error_log("LabResultService::notifyLabRequest - Lab investigation {$labId} has no data");
                return false;
            }

            // Notify the lab tech
            if (!empty($data['lab_tech_id'])) {
                NotificationService::create(
                    $lab->getAttributes()['lab_tech_id'],
                    'lab_request',
                    'New Lab Test Assignment',
                    "You have been assigned a new lab test",
                    [
                        'lab_id' => $lab->getAttributes()['ID'],
                        'patient_id' => $lab->getAttributes()['patient_id'],
                        'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test'
                    ]
                );

                WebSocketService::sendMessage('lab_assignment', [
                    'lab_id' => $lab->getAttributes()['ID'],
                    'sample_type' => $lab->getAttributes()['sample_type'] ?? 'Lab Test',
                    'patient_id' => $lab->getAttributes()['patient_id']
                ], $lab->getAttributes()['lab_tech_id']);
            }

            return true;
        } catch (\Exception $e) {
            error_log("LabResultService::notifyLabRequest - Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Notify about results ready
     */
    public static function notifyResultsReady($labId)
    {
        // Used to call updateLabResults($labId, null), which re-saved the
        // investigation with null results — wiping what had just been entered.
        $lab = LabInvestigation::find($labId);
        if (!$lab) {
            return false;
        }

        self::sendResultNotifications($lab);
        return true;
    }

    /**
     * Get pending lab investigations for lab technicians
     */
    public static function getPendingInvestigations($techId = null, $page = 1, $perPage = 20)
    {
        $cache_params = ['techId' => $techId, 'page' => $page, 'perPage' => $perPage];
        return self::executeCached('getPendingInvestigations', $cache_params, function() use ($techId, $perPage) {
            return LabInvestigation::getPendingForTech($techId, $perPage);
        }, 300); // Cache for 5 minutes (frequently changing)
    }

    /**
     * Get lab results for a specific patient
     */
    public static function getPatientResults($patientId, $page = 1, $perPage = 20)
    {
        $cache_params = ['patientId' => $patientId, 'page' => $page, 'perPage' => $perPage];
        return self::executeCached('getPatientResults', $cache_params, function() use ($patientId, $perPage) {
            return LabInvestigation::getForPatient($patientId, $perPage);
        }, 1200); // Cache for 20 minutes
    }

    /**
     * Get dashboard statistics for lab
     */
    public static function getDashboardStats($techId = null)
    {
        return self::executeCached('getDashboardStats', ['techId' => $techId], function() use ($techId) {
            return self::getDashboardStatsUncached($techId);
        }, 1800); // Cache for 30 minutes
    }

    /**
     * Get dashboard statistics without caching (internal method)
     */
    private static function getDashboardStatsUncached($techId = null)
    {
        $wpdb = self::getWpdb();
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

        // Capture before updating — reading it afterwards logged the new status as "old"
        $old_status = $lab->getAttributes()['status'] ?? 'unknown';

        $success = $lab->updateStatus($status);
        
        if ($success) {
            // Log status change
            AuditLogger::log(
                'update_lab_status',
                'lab_investigation',
                $lab->getAttributes()['ID'],
                [
                    'old_status' => $old_status,
                    'new_status' => $status,
                    'patient_id' => $lab->getAttributes()['patient_id'],
                    'updated_by' => get_current_user_id()
                ]
            );
        }
        
        return $success;
    }
}
