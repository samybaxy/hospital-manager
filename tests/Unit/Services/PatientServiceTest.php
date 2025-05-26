<?php

namespace HospitalManager\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use HospitalManager\Tests\Mocks\Services\MockPatient;
use HospitalManager\Tests\Mocks\Services\MockPatientService;
use HospitalManager\Tests\Mocks\Services\MockAuditLogger;
use Mockery;

// Using centralized MockAuditLogger from HospitalManager\Tests\Mocks\Services namespace

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
            'phone' => '08012345678',
            'gender' => 'M',
            'age' => 35,
            'bio_data' => 'Test patient'
        ];
        
        // Create the patient
        $patient = MockPatientService::createPatient($patient_data);
        
        // Verify patient was created
        $this->assertNotNull($patient, "createPatient should return a patient object");
        $this->assertEquals($patient_data['first_name'], $patient->first_name, "Patient first name not saved correctly");
        $this->assertEquals($patient_data['last_name'], $patient->last_name, "Patient last name not saved correctly");
        $this->assertEquals($patient_data['phone'], $patient->phone, "Patient phone number not saved correctly");
        
        // Verify audit logging
        $this->assertNotEmpty(MockAuditLogger::$logs, "Patient creation should be audited for compliance and security");
        $this->assertEquals('create_patient', MockAuditLogger::$logs[0]->action, "Incorrect audit action recorded");
        $this->assertEquals('patient', MockAuditLogger::$logs[0]->entity_type, "Incorrect audit entity type");
        $this->assertEquals($patient->ID, MockAuditLogger::$logs[0]->entity_id, "Incorrect patient ID in audit log");
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
            'phone' => '08011112222',
            'gender' => 'F',
            'age' => 28,
            'bio_data' => 'Original bio data'
        ];
        
        $patient = MockPatientService::createPatient($original_data);
        
        // Reset audit logs
        MockAuditLogger::reset();
        
        // Update data
        $update_data = [
            'first_name' => 'Updated',
            'phone' => '08033334444',
            // Required fields to satisfy validation
            'last_name' => 'Patient',
            'gender' => 'F',
            'age' => 28,
            'bio_data' => 'Updated bio data'
        ];
        
        // Update the patient
        $result = MockPatientService::updatePatient($patient->ID, $update_data);
        
        // Verify update was successful
        $this->assertNotNull($result, "updatePatient should return the updated patient object");
        $this->assertEquals('Updated', $result->first_name, "Patient first name was not updated");
        $this->assertEquals('08033334444', $result->phone, "Patient phone number was not updated");
        $this->assertEquals('Patient', $result->last_name, "Patient last name should not have changed");
        
        // Verify audit logging for update
        $this->assertNotEmpty(MockAuditLogger::$logs, "Patient updates should be audited for compliance tracking");
        $this->assertEquals('update_patient', MockAuditLogger::$logs[0]->action, "Incorrect audit action for update");
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
            'phone' => '08055556666',
            'gender' => 'M',
            'age' => 35,
            'bio_data' => 'Get patient bio data'
        ];
        
        $created_patient = MockPatientService::createPatient($patient_data);
        
        // Get the patient
        $patient = MockPatientService::getPatient($created_patient->ID);
        
        // Verify retrieved patient
        $this->assertNotNull($patient, "getPatient should return a patient object");
        $this->assertEquals($created_patient->ID, $patient->ID, "Retrieved patient ID doesn't match");
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
            'phone' => '08012345678',
            'gender' => 'M',
            'age' => 42,
            'bio_data' => 'Delete patient bio data'
        ];
        
        $patient = MockPatientService::createPatient($patient_data);
        $patient_id = $patient->ID;
        
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
        $this->assertEquals('delete_patient', MockAuditLogger::$logs[0]->action, "Incorrect audit action for deletion");
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
                'phone' => '08011112222',
                'gender' => 'M',
                'age' => 30,
                'bio_data' => 'Search patient bio data'
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
