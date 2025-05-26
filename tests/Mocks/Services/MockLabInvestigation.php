<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Centralized Mock LabInvestigation for testing
 * 
 * This class provides a unified mock implementation of a Lab Investigation
 * that can be used across multiple test cases.
 */
class MockLabInvestigation 
{
    public $ID;
    public $patient_id;
    public $doctor_id;
    public $lab_tech_id;
    public $test_type;
    public $status;
    public $results;
    public $created_at;
    public $notes;
    public $visitation_id;
    
    public function __construct($data = []) 
    {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }
    
    public function save() 
    {
        // Mock save method
        return true;
    }
    
    /**
     * Static method to find a lab investigation
     */
    public static function find($ID) 
    {
        global $mockLabInvestigations;
        
        if (is_array($mockLabInvestigations) && isset($mockLabInvestigations[$ID])) {
            return $mockLabInvestigations[$ID];
        }
        
        return null;
    }
}
