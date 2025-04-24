<?php

namespace HospitalManager\Tests\Unit\Services;

use HospitalManager\Tests\TestCase;
use HospitalManager\Services\PatientService;
use HospitalManager\Models\Patient;
use HospitalManager\Services\AuditLogger;
use Brain\Monkey\Functions;
use Mockery;

class PatientServiceTest extends TestCase
{
    /**
     * @var array Mock storage for patients
     */
    private $mock_patients = [];
    
    /**
     * @var int Next ID for mock patients
     */
    private $next_patient_id = 1;
    
    /**
     * @var bool Flag to track if audit logs were created
     */
    private $audit_logged = false;
    
    /**
     * @var array Store audit log data for assertions
     */
    private $audit_log_data = [];
    
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Reset test data
        $this->mock_patients = [];
        $this->next_patient_id = 1;
        $this->audit_logged = false;
        $this->audit_log_data = [];
        
        // Mock Patient::create using WordPress-MVC pattern
        Functions\when('Patient::create')->alias(function($data) {
            $id = $this->next_patient_id++;
            $this->mock_patients[$id] = array_merge(['id' => $id], $data);
            return $this->createMockPatient($this->mock_patients[$id]);
        });
        
        // Mock Patient::find
        Functions\when('Patient::find')->alias(function($id) {
            if (isset($this->mock_patients[$id])) {
                return $this->createMockPatient($this->mock_patients[$id]);
            }
            return null;
        });
        
        // Mock Patient::where
        Functions\when('Patient::where')->alias(function($column, $value = null) {
            $results = [];
            
            // Handle different where formats
            if (is_array($column)) {
                // Where with array of conditions
                foreach ($this->mock_patients as $patient) {
                    $match = true;
                    foreach ($column as $key => $val) {
                        if (!isset($patient[$key]) || $patient[$key] != $val) {
                            $match = false;
                            break;
                        }
                    }
                    if ($match) {
                        $results[] = $this->createMockPatient($patient);
                    }
                }
            } else {
                // Simple where with column and value
                foreach ($this->mock_patients as $patient) {
                    if (isset($patient[$column]) && $patient[$column] == $value) {
                        $results[] = $this->createMockPatient($patient);
                    }
                }
            }
            
            return $results;
        });
        
        // Mock AuditLogger::log to track audit logging
        Functions\when('AuditLogger::log')->alias(function($action, $entityType, $entityId, $details, $userId = null) {
            $this->audit_logged = true;
            $this->audit_log_data = [
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'details' => $details,
                'user_id' => $userId
            ];
            return true;
        });
        
        // Mock WordPress filter system
        Functions\when('apply_filters')->alias(function($tag, $value, ...$args) {
            if ($tag === 'hospital_manager_before_patient_create') {
                // Allow pre-filtering of patient data
                return $value;
            } elseif ($tag === 'hospital_manager_after_patient_create') {
                // Handle post-creation filtering
                return $args[0];
            }
            return $value;
        });
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
     * Create a mock patient object
     */
    private function createMockPatient($data)
    {
        $patient = Mockery::mock(Patient::class);
        
        // Set up properties
        foreach ($data as $key => $value) {
            $patient->{$key} = $value;
        }
        
        // Allow saving
        $patient->shouldReceive('save')
            ->andReturnUsing(function() use ($patient) {
                $this->mock_patients[$patient->id] = (array)$patient;
                return true;
            });
            
        // Allow deleting
        $patient->shouldReceive('delete')
            ->andReturnUsing(function() use ($patient) {
                if (isset($this->mock_patients[$patient->id])) {
                    unset($this->mock_patients[$patient->id]);
                }
                return true;
            });
            
        return $patient;
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
        $patient = PatientService::createPatient($patient_data);
        
        // Verify patient was created
        $this->assertInstanceOf(Patient::class, $patient, "createPatient should return a Patient instance");
        $this->assertEquals($patient_data['first_name'], $patient->first_name, "Patient first name not saved correctly");
        $this->assertEquals($patient_data['last_name'], $patient->last_name, "Patient last name not saved correctly");
        $this->assertEquals($patient_data['phone_number'], $patient->phone_number, "Patient phone number not saved correctly");
        
        // Verify audit logging
        $this->assertTrue($this->audit_logged, "Patient creation should be audited for compliance and security");
        $this->assertEquals('create_patient', $this->audit_log_data['action'], "Incorrect audit action recorded");
        $this->assertEquals('patient', $this->audit_log_data['entity_type'], "Incorrect audit entity type");
        $this->assertEquals($patient->id, $this->audit_log_data['entity_id'], "Incorrect patient ID in audit log");
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
        $patient = PatientService::createPatient($invalid_data);
        $this->assertFalse($patient, "Creating a patient with invalid data should return false");
        
        // No audit log should be created for failed attempts
        $this->assertFalse($this->audit_logged, "Failed patient creation attempts should not create audit logs");
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
        
        $patient = PatientService::createPatient($original_data);
        
        // Reset audit log flag
        $this->audit_logged = false;
        
        // Update data
        $update_data = [
            'first_name' => 'Updated',
            'phone_number' => '08033334444'
        ];
        
        // Update the patient
        $result = PatientService::updatePatient($patient->id, $update_data);
        
        // Verify update was successful
        $this->assertInstanceOf(Patient::class, $result, "updatePatient should return the updated Patient instance");
        $this->assertEquals('Updated', $result->first_name, "Patient first name was not updated");
        $this->assertEquals('08033334444', $result->phone_number, "Patient phone number was not updated");
        $this->assertEquals('Patient', $result->last_name, "Patient last name should not have changed");
        
        // Verify audit logging for update
        $this->assertTrue($this->audit_logged, "Patient updates should be audited for compliance tracking");
        $this->assertEquals('update_patient', $this->audit_log_data['action'], "Incorrect audit action for update");
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
        $result = PatientService::updatePatient(9999, $update_data);
        
        // Should return false
        $this->assertFalse($result, "Updating a non-existent patient should return false");
        $this->assertFalse($this->audit_logged, "Failed updates should not create audit logs");
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
            'phone_number' => '08055556666'
        ];
        
        $created_patient = PatientService::createPatient($patient_data);
        
        // Get the patient
        $patient = PatientService::getPatient($created_patient->id);
        
        // Verify retrieved patient
        $this->assertInstanceOf(Patient::class, $patient, "getPatient should return a Patient instance");
        $this->assertEquals($created_patient->id, $patient->id, "Retrieved patient ID doesn't match");
        $this->assertEquals('Get', $patient->first_name, "Retrieved patient first name doesn't match");
        $this->assertEquals('Patient', $patient->last_name, "Retrieved patient last name doesn't match");
    }
    
    /**
     * Test getting a non-existent patient
     */
    public function testGetNonExistentPatient()
    {
        $patient = PatientService::getPatient(9999);
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
            'last_name' => 'Patient'
        ];
        
        $patient = PatientService::createPatient($patient_data);
        $patient_id = $patient->id;
        
        // Reset audit log flag
        $this->audit_logged = false;
        
        // Delete the patient
        $result = PatientService::deletePatient($patient_id);
        
        // Verify deletion was successful
        $this->assertTrue($result, "deletePatient should return true on success");
        
        // Verify patient was actually deleted
        $this->assertNull(PatientService::getPatient($patient_id), "Patient should not be retrievable after deletion");
        
        // Verify audit logging for deletion
        $this->assertTrue($this->audit_logged, "Patient deletion should be audited for compliance tracking");
        $this->assertEquals('delete_patient', $this->audit_log_data['action'], "Incorrect audit action for deletion");
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
            PatientService::createPatient(array_merge($data, [
                'phone_number' => '08011112222',
                'sex' => 'M',
                'age' => 30
            ]));
        }
        
        // Search for patients with 'Doe' in their name
        $results = PatientService::searchPatients('Doe');
        
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
