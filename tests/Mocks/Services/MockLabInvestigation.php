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
    public $id;
    public $patient_id;
    public $doctor_id;
    public $lab_tech_id;
    public $requested_by;
    public $test_type;
    public $status;
    public $results;
    public $report_url;
    public $completed_at;
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
    public static function find($id) 
    {
        global $mockLabInvestigations;
        
        if (is_array($mockLabInvestigations) && isset($mockLabInvestigations[$id])) {
            return $mockLabInvestigations[$id];
        }
        
        return null;
    }
}
