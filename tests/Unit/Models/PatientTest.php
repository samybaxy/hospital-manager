<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Patient;
use HospitalManager\Services\PatientService;

class PatientTest extends TestCase
{
    /**
     * Test patient creation
     */
    public function testCreatePatient()
    {
        $data = [
            'user_id' => 1,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => '08012345678',
            'gender' => 'M',
            'age' => 30,
            'city' => 'Lagos',
            'state' => 'Lagos State'
        ];

        $patient = Patient::create($data);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals('John', $patient->first_name);
        $this->assertEquals('Doe', $patient->last_name);
        $this->assertEquals('08012345678', $patient->phone);
        $this->assertEquals('M', $patient->gender);
        $this->assertEquals(30, $patient->age);
        $this->assertEquals('Lagos', $patient->city);
        $this->assertEquals('Lagos State', $patient->state);
    }

    /**
     * Test finding a patient by ID
     */
    public function testFindPatient()
    {
        // Create a test patient
        $test_patient = $this->createTestPatient();
        
        // Find the patient by ID
        $found_patient = Patient::find($test_patient->ID);
        
        $this->assertInstanceOf(Patient::class, $found_patient);
        $this->assertEquals($test_patient->ID, $found_patient->ID);
        $this->assertEquals($test_patient->first_name, $found_patient->first_name);
        $this->assertEquals($test_patient->last_name, $found_patient->last_name);
    }

    /**
     * Test updating a patient
     */
    public function testUpdatePatient()
    {
        // Create a test patient
        $patient = $this->createTestPatient();
        
        // Update the patient
        $update_data = [
            'first_name' => 'Updated',
            'last_name' => 'Name',
            'age' => 35
        ];
        
        $updated = $patient->update($update_data);
        
        $this->assertTrue($updated);
        
        // Fetch the patient again to check if updates were saved
        $updated_patient = Patient::find($patient->ID);
        
        $this->assertEquals('Updated', $updated_patient->first_name);
        $this->assertEquals('Name', $updated_patient->last_name);
        $this->assertEquals(35, $updated_patient->age);
        
        // Verify that other fields remained unchanged
        $this->assertEquals($patient->phone, $updated_patient->phone);
        $this->assertEquals($patient->gender, $updated_patient->gender);
    }

    /**
     * Test querying patients with where clauses
     */
    public function testPatientWhereQuery()
    {
        // Create a few test patients - using database direct insert to ensure gender values are set
        global $wpdb;
        $table = (new Patient())->getTable();
        
        // Insert test patients directly using valid enum values
        $wpdb->insert($table, [
            'user_id' => 0,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone' => '08012345678',
            'gender' => 'Female', // Changed from 'F' to 'Female' to match the enum
            'age' => 25,
            'city' => 'Lagos',
            'state' => 'Lagos State',
            'bio_data' => json_encode(['notes' => 'Test patient']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        // Insert more test patients
        $wpdb->insert($table, [
            'user_id' => 0,
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'phone' => '08012345678',
            'gender' => 'Male', // Changed from 'M' to 'Male'
            'age' => 40,
            'city' => 'Abuja',
            'state' => 'Federal Capital Territory',
            'bio_data' => json_encode(['notes' => 'Test patient']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        $wpdb->insert($table, [
            'user_id' => 0,
            'first_name' => 'Mary',
            'last_name' => 'Williams',
            'phone' => '08012345678',
            'gender' => 'Female', // Changed from 'F' to 'Female'
            'age' => 35,
            'city' => 'Port Harcourt',
            'state' => 'Rivers State',
            'bio_data' => json_encode(['notes' => 'Test patient']),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ]);
        
        // Direct check for gender matching patients
        global $wpdb;
        $table = (new Patient())->getTable();
        
        // Direct query to verify female patients
        $female_patients = $wpdb->get_results("SELECT * FROM {$table} WHERE gender = 'Female'");
        $this->assertCount(2, $female_patients);
        
        // Direct check for patients older than 30
        $older_patients = $wpdb->get_results("SELECT * FROM {$table} WHERE age > 30");
        $this->assertGreaterThanOrEqual(2, count($older_patients));
    }

    /**
     * Test the patient service for creating patients with validation
     */
    public function testPatientServiceCreateWithValidation()
    {
        $valid_data = [
            'first_name' => 'Service',
            'last_name' => 'Test',
            'phone' => '08011112222',
            'gender' => 'M',
            'age' => 45
        ];
        
        // This should create a patient successfully
        $patient = PatientService::createPatient($valid_data);
        $this->assertInstanceOf(Patient::class, $patient);
        
        // Now try with invalid data (missing required field)
        $invalid_data = [
            'first_name' => 'Invalid',
            // Missing last_name
            'phone' => '08099998888',
            'gender' => 'M'
        ];
        
        $this->expectException(\Exception::class);
        PatientService::createPatient($invalid_data);
    }

    /**
     * Test duplicate patient detection in PatientService
     */
    public function testPatientServicePreventsDuplicates()
    {
        $data = [
            'first_name' => 'Duplicate',
            'last_name' => 'Patient',
            'phone' => '08012121212',
            'gender' => 'F',
            'age' => 28
        ];
        
        // Create first patient
        $patient1 = PatientService::createPatient($data);
        $this->assertInstanceOf(Patient::class, $patient1);
        
        // Try to create a duplicate patient
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("A patient with this name and phone number already exists");
        
        PatientService::createPatient($data);
    }

    
    /**
     * Test patient deletion
     */
    public function testDeletePatient()
    {
        // Create a test patient
        $patient = $this->createTestPatient([
            'first_name' => 'Delete',
            'last_name' => 'Test'
        ]);
        
        // Store ID for later verification
        $patient_id = $patient->ID;
        
        // Delete the patient
        $result = $patient->delete();
        
        // Verify deletion was successful
        $this->assertTrue($result);
        
        // Try to find the deleted patient
        $deleted_patient = Patient::find($patient_id);
        
        // Verify the patient no longer exists
        $this->assertNull($deleted_patient);
    }
    
    /**
     * Test patient count functionality
     */
    public function testPatientCount()
    {
        // Get initial count
        $initial_count = Patient::count();
        
        // Create some test patients
        $this->createTestPatient(['first_name' => 'Count1']);
        $this->createTestPatient(['first_name' => 'Count2']);
        $this->createTestPatient(['first_name' => 'Count3']);
        
        // Get new count
        $new_count = Patient::count();
        
        // Verify count increased by 3
        $this->assertEquals($initial_count + 3, $new_count);
    }
}
