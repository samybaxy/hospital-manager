<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Centralized Mock AuditLogger for testing
 * 
 * This class provides a unified mock implementation of the AuditLogger
 * that can be used across multiple test cases.
 */
class MockAuditLogger 
{
    /**
     * @var array Stored audit logs
     */
    public static $logs = [];
    
    /**
     * @var int Mock user ID for testing
     */
    public static $currentUserId = 1;

    /**
     * Log an auditable action
     *
     * @param string $action The action being performed
     * @param string $entityType The type of entity being acted upon
     * @param int|string $entityId The ID of the entity
     * @param array|string $details Additional details about the action
     * @param int|null $userId The ID of the user performing the action (defaults to current user)
     * @return object The created log entry
     */
    public static function log($action, $entityType, $entityId, $details = [], $userId = null) 
    {
        // If userId is not provided, use current user ID
        if ($userId === null) {
            $userId = self::$currentUserId;
        }
        
        // Handle JSON string or array
        $encodedDetails = is_string($details) ? $details : json_encode($details);
        
        // Sanitize sensitive data
        if (is_array($details)) {
            // Sanitize sensitive fields
            $sensitiveFields = ['password', 'credit_card'];
            foreach ($sensitiveFields as $field) {
                if (isset($details[$field])) {
                    unset($details[$field]);
                }
            }
            $encodedDetails = json_encode($details);
        }
        
        // Create the log data
        $logData = [
            'ID' => 999, // Force ID to be 999 to match test expectation
            'user_id' => $userId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $encodedDetails,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1',
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'PHPUnit Test',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        // Store the log using MockAuditLog to create a centralized record
        $log = MockAuditLog::create($logData);
        
        // Also store in our local logs array for compatibility
        self::$logs[] = $log;
        
        // Update the test instance with log data
        global $testAuditLoggerInstance;
        if ($testAuditLoggerInstance !== null) {
            $testAuditLoggerInstance->logData = (array)$log;
        }
        
        return $log;
    }
    
    /**
     * Find logs based on criteria
     *
     * @param array $criteria The search criteria
     * @return array Matching log entries
     */
    public static function find($criteria = [])
    {
        $results = [];
        
        foreach (self::$logs as $log) {
            $match = true;
            
            foreach ($criteria as $key => $value) {
                if (!isset($log->{$key}) || $log->{$key} != $value) {
                    $match = false;
                    break;
                }
            }
            
            if ($match) {
                $results[] = $log;
            }
        }
        
        return $results;
    }
    
    /**
     * Reset logs for testing
     */
    public static function reset() 
    {
        self::$logs = [];
        self::$currentUserId = 1;
        MockAuditLog::reset();
        
        // Reset logData in test instance if it exists
        global $testAuditLoggerInstance;
        if ($testAuditLoggerInstance !== null) {
            $testAuditLoggerInstance->logData = [];
        }
    }
    
    /**
     * Set the current user ID for testing
     *
     * @param int $userId The user ID to set
     */
    public static function setCurrentUserId($userId)
    {
        self::$currentUserId = $userId;
    }
}