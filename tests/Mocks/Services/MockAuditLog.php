<?php

namespace HospitalManager\Tests\Mocks\Services;

use HospitalManager\Tests\Mocks\Services\MockQueryBuilder;

/**
 * Centralized Mock AuditLog for testing
 * 
 * This class provides a unified mock implementation of the AuditLog
 * that can be used across multiple test cases.
 */
class MockAuditLog 
{
    /**
     * @var array Stored mock log entries
     */
    public static $mockLogs = [];
    
    /**
     * @var int Auto-incrementing ID for new log entries
     */
    public static $nextId = 1;
    
    /**
     * Create a mock audit log entry
     *
     * @param array $data Data for the audit log
     * @return object The created log entry
     */
    public static function create($data) 
    {
        // Use the provided ID if it exists, otherwise auto-increment
        $id = isset($data['id']) ? $data['id'] : self::$nextId++;
        $log = (object)array_merge(['id' => $id], $data);
        self::$mockLogs[$id] = $log;
        return $log;
    }
    
    /**
     * Mock where method for querying logs
     *
     * @param mixed $column Either a column name or an array of conditions
     * @param mixed $value The value to match (when $column is a string)
     * @return MockQueryBuilder Query builder for the filtered results
     */
    public static function where($column, $value = null) 
    {
        $results = [];
        
        // Handle different where formats
        if (is_array($column)) {
            // Where with array of conditions
            foreach (self::$mockLogs as $log) {
                $match = true;
                foreach ($column as $key => $val) {
                    if (!isset($log->$key) || $log->$key != $val) {
                        $match = false;
                        break;
                    }
                }
                if ($match) {
                    $results[] = $log;
                }
            }
        } else {
            // Simple where with column and value
            foreach (self::$mockLogs as $log) {
                if (isset($log->$column) && $log->$column == $value) {
                    $results[] = $log;
                }
            }
        }
        
        // Return a mock query builder
        return new MockQueryBuilder($results);
    }
    
    /**
     * Find a log by ID
     *
     * @param int $id The ID of the log to find
     * @return object|null The log entry if found, or null
     */
    public static function find($id) 
    {
        return isset(self::$mockLogs[$id]) ? self::$mockLogs[$id] : null;
    }
    
    /**
     * Reset all logs for testing
     */
    public static function reset()
    {
        self::$mockLogs = [];
        self::$nextId = 1;
    }
}
