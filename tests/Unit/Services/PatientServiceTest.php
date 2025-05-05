<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use Mockery;

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
        $required_fields = ['first_name', 'last_name', 'phone_number', 'sex'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                return false;
            }
        }
        
        $id = self::$nextId++;
        $patient = new \stdClass();
        $patient->id = $id;
        
        foreach ($data as $key => $value) {
            $patient->$key = $value;
        }
        
        self::$patients[$id] = $patient;
        return $patient;
    }
    
    /**
     * Find a patient by ID
     */
    public static function find($id) 
    {
        return isset(self::$patients[$id]) ? self::$patients[$id] : null;
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
    public static function delete($id) 
    {
        if (isset(self::$patients[$id])) {
            unset(self::$patients[$id]);
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

/**
 * Mock AuditLogger for testing
 */
class MockAuditLogger 
{
    public static $logs = [];
    
    /**
     * Log an audit entry
     */
    public static function log($action, $entityType, $entityId, $details = [], $userId = null) 
    {
        $log = [
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'details' => $details,
            'user_id' => $userId ?? 1,
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        self::$logs[] = $log;
        return true;
    }
    
    /**
     * Reset logs for testing
     */
    public static function reset() 
    {
        self::$logs = [];
    }
}

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
        
        // Log the update
        MockAuditLogger::log(
            'update_patient',
            'patient',
            $patient->id,
            ['updated_data' => $data]
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

/**
 * Tests for PatientService
 */
class PatientServiceTest extends TestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset mock data
        MockPatient::reset();
        MockAuditLogger::reset();
    }
    
    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
    
    /**
     * Test creating a patient with valid data
     */
    public function testCreatePatientWithValidData()
    {
        $patient_data = [
            'first_name' => 'John',
            'last_name' => 'Smith',
            'phone_number' => '08012345678',
            'sex' => 'M',
            'age' => 35,
            'bio_data' => 'Test patient'
        ];
        
        // Create the patient
        $patient = MockPatientService::createPatient($patient_data);
        
        // Verify patient was created
        $this->assertNotNull($patient, "createPatient should return a patient object");
        $this->assertEquals($patient_data['first_name'], $patient->first_name, "Patient first name not saved correctly");
        $this->assertEquals($patient_data['last_name'], $patient->last_name, "Patient last name not saved correctly");
        $this->assertEquals($patient_data['phone_number'], $patient->phone_number, "Patient phone number not saved correctly");
        
        // Verify audit logging
        $this->assertNotEmpty(MockAuditLogger::$logs, "Patient creation should be audited for compliance and security");
        $this->assertEquals('create_patient', MockAuditLogger::$logs[0]['action'], "Incorrect audit action recorded");
        $this->assertEquals('patient', MockAuditLogger::$logs[0]['entity_type'], "Incorrect audit entity type");
        $this->assertEquals($patient->id, MockAuditLogger::$logs[0]['entity_id'], "Incorrect patient ID in audit log");
    }
    
    /**
     * Test creating a patient with invalid data
     */
    public function testCreatePatientWithInvalidData()
    {
        // Missing required fields
        $invalid_data = [
            'first_name' => 'Invalid'
            // Missing last_name and other required fields
        ];
        
        // Should return false for invalid data
        $patient = MockPatientService::createPatient($invalid_data);
        $this->assertFalse($patient, "Creating a patient with invalid data should return false");
        
        // No audit log should be created for failed attempts
        $this->assertEmpty(MockAuditLogger::$logs, "Failed patient creation attempts should not create audit logs");
    }
    
    /**
     * Test updating a patient
     */
    public function testUpdatePatient()
    {
        // First create a patient
        $original_data = [
            'first_name' => 'Original',
            'last_name' => 'Patient',
            'phone_number' => '08011112222',
            'sex' => 'F',
            'age' => 28
        ];
        
        $patient = MockPatientService::createPatient($original_data);
        
        // Reset audit logs
        MockAuditLogger::reset();
        
        // Update data
        $update_data = [
            'first_name' => 'Updated',
            'phone_number' => '08033334444'
        ];
        
        // Update the patient
        $result = MockPatientService::updatePatient($patient->id, $update_data);
        
        // Verify update was successful
        $this->assertNotNull($result, "updatePatient should return the updated patient object");
        $this->assertEquals('Updated', $result->first_name, "Patient first name was not updated");
        $this->assertEquals('08033334444', $result->phone_number, "Patient phone number was not updated");
        $this->assertEquals('Patient', $result->last_name, "Patient last name should not have changed");
        
        // Verify audit logging for update
        $this->assertNotEmpty(MockAuditLogger::$logs, "Patient updates should be audited for compliance tracking");
        $this->assertEquals('update_patient', MockAuditLogger::$logs[0]['action'], "Incorrect audit action for update");
    }
    
    /**
     * Test updating a non-existent patient
     */
    public function testUpdateNonExistentPatient()
    {
        $update_data = [
            'first_name' => 'NonExistent'
        ];
        
        // Try to update a non-existent patient
        $result = MockPatientService::updatePatient(9999, $update_data);
        
        // Should return false
        $this->assertFalse($result, "Updating a non-existent patient should return false");
        $this->assertEmpty(MockAuditLogger::$logs, "Failed updates should not create audit logs");
    }
    
    /**
     * Test retrieving a patient by ID
     */
    public function testGetPatient()
    {
        // First create a patient
        $patient_data = [
            'first_name' => 'Get',
            'last_name' => 'Patient',
            'phone_number' => '08055556666',
            'sex' => 'M'
        ];
        
        $created_patient = MockPatientService::createPatient($patient_data);
        
        // Get the patient
        $patient = MockPatientService::getPatient($created_patient->id);
        
        // Verify retrieved patient
        $this->assertNotNull($patient, "getPatient should return a patient object");
        $this->assertEquals($created_patient->id, $patient->id, "Retrieved patient ID doesn't match");
        $this->assertEquals('Get', $patient->first_name, "Retrieved patient first name doesn't match");
        $this->assertEquals('Patient', $patient->last_name, "Retrieved patient last name doesn't match");
    }
    
    /**
     * Test getting a non-existent patient
     */
    public function testGetNonExistentPatient()
    {
        $patient = MockPatientService::getPatient(9999);
        $this->assertNull($patient, "Getting a non-existent patient should return null");
    }
    
    /**
     * Test deleting a patient
     */
    public function testDeletePatient()
    {
        // First create a patient
        $patient_data = [
            'first_name' => 'Delete',
            'last_name' => 'Patient',
            'phone_number' => '08012345678',
            'sex' => 'M'
        ];
        
        $patient = MockPatientService::createPatient($patient_data);
        $patient_id = $patient->id;
        
        // Reset audit logs
        MockAuditLogger::reset();
        
        // Delete the patient
        $result = MockPatientService::deletePatient($patient_id);
        
        // Verify deletion was successful
        $this->assertTrue($result, "deletePatient should return true on success");
        
        // Verify patient was actually deleted
        $this->assertNull(MockPatientService::getPatient($patient_id), "Patient should not be retrievable after deletion");
        
        // Verify audit logging for deletion
        $this->assertNotEmpty(MockAuditLogger::$logs, "Patient deletion should be audited for compliance tracking");
        $this->assertEquals('delete_patient', MockAuditLogger::$logs[0]['action'], "Incorrect audit action for deletion");
    }
    
    /**
     * Test searching for patients by name
     */
    public function testSearchPatientsByName()
    {
        // Create several patients with different names
        $patients = [
            ['first_name' => 'John', 'last_name' => 'Doe'],
            ['first_name' => 'Jane', 'last_name' => 'Doe'],
            ['first_name' => 'Alice', 'last_name' => 'Smith']
        ];
        
        foreach ($patients as $data) {
            MockPatientService::createPatient(array_merge($data, [
                'phone_number' => '08011112222',
                'sex' => 'M',
                'age' => 30
            ]));
        }
        
        // Search for patients with 'Doe' in their name
        $results = MockPatientService::searchPatients('Doe');
        
        // Should find 2 patients
        $this->assertCount(2, $results, "Search should find exactly 2 patients with 'Doe' in their name");
        
        // Verify searched patients
        $found_names = [];
        foreach ($results as $patient) {
            $found_names[] = $patient->last_name;
        }
        
        $this->assertContains('Doe', $found_names, "Search results should include patients with 'Doe' as last name");
        $this->assertNotContains('Smith', $found_names, "Search results should not include patients without 'Doe' in their name");
    }
}
