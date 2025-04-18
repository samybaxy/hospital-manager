<?php

namespace HospitalManager\Services;

use HospitalManager\Models\AuditLog;

class AuditLogger
{
    /**
     * Log an action in the audit trail
     * 
     * @param string $action The action being performed (e.g., 'create_visitation', 'update_biodata')
     * @param string $entityType The type of entity being affected (e.g., 'patient', 'visitation')
     * @param int $entityId The ID of the entity being affected
     * @param array $details Additional details about the action
     * @return bool Whether the log was created successfully
     */
    public static function log($action, $entityType, $entityId, $details = [])
    {
        try {
            AuditLog::create([
                'user_id' => get_current_user_id(),
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => json_encode($details),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'created_at' => current_time('mysql')
            ]);

            return true;
        } catch (\Exception $e) {
            // Log the error but don't disrupt the application flow
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get formatted description of an audit log entry
     * 
     * @param string $action The action that was performed
     * @param array $details The details of the action
     * @return string A human-readable description of the action
     */
    public static function getFormattedDescription($action, $details)
    {
        $descriptions = [
            'create_visitation' => 'Created new visitation record',
            'update_biodata' => 'Updated patient biodata',
            'create_lab_request' => 'Requested lab investigation',
            'update_lab_results' => 'Updated lab results',
            'view_patient' => 'Viewed patient record',
            'update_patient' => 'Updated patient information'
        ];

        return $descriptions[$action] ?? 'Performed ' . str_replace('_', ' ', $action);
    }

    /**
     * Check if an entity type should be audited
     * 
     * @param string $entityType The type of entity to check
     * @return bool Whether the entity type should be audited
     */
    public static function shouldAudit($entityType)
    {
        $auditedTypes = [
            'patient',
            'visitation',
            'lab_investigation',
            'prescription',
            'medical_history'
        ];

        return in_array($entityType, $auditedTypes);
    }
}
