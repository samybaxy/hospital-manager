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
     * @param array|string $details Additional details about the action (array or JSON string)
     * @param int|null $userId Optional user ID, uses current user if not provided
     * @return AuditLog|bool The created log entry or false on failure
     */
    public static function log($action, $entityType, $entityId, $details = [], $userId = null)
    {
        try {
            // If userId is not provided, use current user
            if ($userId === null) {
                $userId = get_current_user_id();
            }
            
            // Handle details - if it's already a JSON string, don't re-encode
            if (is_string($details) && self::isJson($details)) {
                $encodedDetails = $details;
            } else {
                // Sanitize sensitive data
                if (is_array($details)) {
                    // Remove sensitive information
                    if (isset($details['password'])) {
                        unset($details['password']);
                    }
                    
                    // Mask credit card numbers if present
                    if (isset($details['credit_card'])) {
                        $details['credit_card'] = preg_replace('/[0-9](?=([0-9]{4}))/', '*', $details['credit_card']);
                    }
                }
                
                // Encode the details
                $encodedDetails = json_encode($details);
                
                // If encoding fails, provide a fallback
                if ($encodedDetails === false) {
                    $encodedDetails = json_encode(['error' => 'Unable to encode details']);
                }
            }
            
            // Get IP address using filter to allow customizing in tests
            $ipAddress = apply_filters('hospital_manager_user_ip', $_SERVER['REMOTE_ADDR'] ?? null);
            
            // Get user agent using filter to allow customizing in tests
            $userAgent = apply_filters('hospital_manager_user_agent', $_SERVER['HTTP_USER_AGENT'] ?? null);

            // Allow pre-filtering of audit log data
            $logData = apply_filters('pre_audit_log_create', [
                'user_id' => $userId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $encodedDetails,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'created_at' => current_time('mysql')
            ]);

            // Create the audit log entry
            $logEntry = AuditLog::create($logData);
            
            // Allow post-processing of the created log
            $logEntry = apply_filters('after_audit_log_create', $logEntry, $logData);

            return $logEntry;
        } catch (\Exception $e) {
            // Log the error but don't disrupt the application flow
            error_log("Audit logging failed: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if a string is valid JSON
     *
     * @param string $string The string to check
     * @return bool Whether the string is valid JSON
     */
    private static function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
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
