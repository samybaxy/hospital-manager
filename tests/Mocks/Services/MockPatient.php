<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Mock Patient class for testing
 */
class MockPatient 
{
    private static $patients = [];
    private static $nextId = 1;
    
    /**
     * Create a new patient
     */
    public static function create($data) 
    {
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'phone', 'gender', 'age', 'bio_data'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        $ID = self::$nextId++;
        $patient = new \stdClass();
        $patient->ID = $ID;
        
        foreach ($data as $key => $value) {
            $patient->$key = $value;
        }
        
        self::$patients[$ID] = $patient;
        return $patient;
    }
    
    /**
     * Find a patient by ID
     */
    public static function find($ID) 
    {
        return isset(self::$patients[$ID]) ? self::$patients[$ID] : null;
    }
    
    /**
     * Where clause for finding patients
     */
    public static function where($column, $value = null) 
    {
        $results = [];
        
        // Check each patient
        foreach (self::$patients as $patient) {
            if (isset($patient->$column) && $patient->$column === $value) {
                $results[] = $patient;
            }
        }
        
        return $results;
    }
    
    /**
     * Delete a patient
     */
    public static function delete($ID) 
    {
        if (isset(self::$patients[$ID])) {
            unset(self::$patients[$ID]);
            return true;
        }
        return false;
    }
    
    /**
     * Reset patients for testing
     */
    public static function reset() 
    {
        self::$patients = [];
        self::$nextId = 1;
    }
    
    /**
     * Search patients
     */
    public static function search($searchTerm) 
    {
        $results = [];
        
        // Case insensitive search in first and last names
        foreach (self::$patients as $patient) {
            if (stripos($patient->first_name, $searchTerm) !== false || 
                stripos($patient->last_name, $searchTerm) !== false) {
                $results[] = $patient;
            }
        }
        
        return $results;
    }
}