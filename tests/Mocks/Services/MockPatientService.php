<?php

namespace HospitalManager\Tests\Mocks\Services;

/**
 * Mock PatientService class for testing
 */
class MockPatientService 
{
    /**
     * Create a new patient
     */
    public static function createPatient($data) 
    {
        // Apply pre-creation filter
        $data = self::applyFilter('hospital_manager_before_patient_create', $data);
        
        // Create the patient
        $patient = MockPatient::create($data);
        
        // If patient creation was successful, log it
        if ($patient) {
            MockAuditLogger::log(
                'create_patient',
                'patient',
                $patient->id,
                ['patient_data' => $data]
            );
            
            // Apply post-creation filter
            return self::applyFilter('hospital_manager_after_patient_create', $patient);
        }
        
        return false;
    }
    
    /**
     * Update an existing patient
     */
    public static function updatePatient($id, $data) 
    {
        $patient = MockPatient::find($id);
        if (!$patient) {
            return false;
        }
        
        // Update the patient fields
        foreach ($data as $key => $value) {
            $patient->$key = $value;
        }
        
        // Get original patient data for "before" state
        $before = [];
        foreach (get_object_vars($patient) as $key => $value) {
            $before[$key] = $value;
        }
        
        // Create "after" state by applying the updates
        $after = $before;
        foreach ($data as $key => $value) {
            $after[$key] = $value;
        }
        
        // Log the update with before/after data
        MockAuditLogger::log(
            'update_patient',
            'patient',
            $patient->id,
            [
                'before' => $before,
                'after' => $after
            ]
        );
        
        return $patient;
    }
    
    /**
     * Get a patient by ID
     */
    public static function getPatient($id) 
    {
        return MockPatient::find($id);
    }
    
    /**
     * Delete a patient
     */
    public static function deletePatient($id) 
    {
        $patient = MockPatient::find($id);
        if (!$patient) {
            return false;
        }
        
        // Delete the patient
        $result = MockPatient::delete($id);
        
        // Log the deletion if successful
        if ($result) {
            MockAuditLogger::log(
                'delete_patient',
                'patient',
                $id,
                ['patient_id' => $id]
            );
        }
        
        return $result;
    }
    
    /**
     * Search for patients by name
     */
    public static function searchPatients($searchTerm) 
    {
        return MockPatient::search($searchTerm);
    }
    
    /**
     * Mock filter application
     */
    private static function applyFilter($tag, $value, ...$args) 
    {
        // Just return the value unchanged for testing
        return $value;
    }
}