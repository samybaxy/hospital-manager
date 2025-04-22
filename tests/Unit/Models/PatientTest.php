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
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '08012345678',
            'sex' => 'M',
            'age' => 30,
            'bio_data' => 'This is a test patient'
        ];

        $patient = Patient::create($data);

        $this->assertInstanceOf(Patient::class, $patient);
        $this->assertEquals('John', $patient->first_name);
        $this->assertEquals('Doe', $patient->last_name);
        $this->assertEquals('08012345678', $patient->phone_number);
        $this->assertEquals('M', $patient->sex);
        $this->assertEquals(30, $patient->age);
    }

    /**
     * Test finding a patient by ID
     */
    public function testFindPatient()
    {
        // Create a test patient
        $test_patient = $this->createTestPatient();
        
        // Find the patient by ID
        $found_patient = Patient::find($test_patient->id);
        
        $this->assertInstanceOf(Patient::class, $found_patient);
        $this->assertEquals($test_patient->id, $found_patient->id);
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
        $updated_patient = Patient::find($patient->id);
        
        $this->assertEquals('Updated', $updated_patient->first_name);
        $this->assertEquals('Name', $updated_patient->last_name);
        $this->assertEquals(35, $updated_patient->age);
        
        // Verify that other fields remained unchanged
        $this->assertEquals($patient->phone_number, $updated_patient->phone_number);
        $this->assertEquals($patient->sex, $updated_patient->sex);
    }

    /**
     * Test querying patients with where clauses
     */
    public function testPatientWhereQuery()
    {
        // Create a few test patients
        $this->createTestPatient([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'sex' => 'F',
            'age' => 25
        ]);
        
        $this->createTestPatient([
            'first_name' => 'Bob',
            'last_name' => 'Johnson',
            'sex' => 'M',
            'age' => 40
        ]);
        
        $this->createTestPatient([
            'first_name' => 'Mary',
            'last_name' => 'Williams',
            'sex' => 'F',
            'age' => 35
        ]);
        
        // Query female patients
        $query = new Patient();
        $female_patients = $query->where('sex', 'F')->get();
        
        $this->assertIsArray($female_patients);
        $this->assertCount(2, $female_patients);
        $this->assertEquals('F', $female_patients[0]->sex);
        $this->assertEquals('F', $female_patients[1]->sex);
        
        // Query patients with age > 30
        $query = new Patient();
        $older_patients = $query->where('age', '>', 30)->get();
        
        $this->assertIsArray($older_patients);
        $this->assertGreaterThanOrEqual(2, count($older_patients));
        foreach ($older_patients as $patient) {
            $this->assertGreaterThan(30, $patient->age);
        }
    }

    /**
     * Test the patient service for creating patients with validation
     */
    public function testPatientServiceCreateWithValidation()
    {
        $valid_data = [
            'first_name' => 'Service',
            'last_name' => 'Test',
            'phone_number' => '08011112222',
            'sex' => 'M',
            'age' => 45
        ];
        
        // This should create a patient successfully
        $patient = PatientService::createPatient($valid_data);
        $this->assertInstanceOf(Patient::class, $patient);
        
        // Now try with invalid data (missing required field)
        $invalid_data = [
            'first_name' => 'Invalid',
            // Missing last_name
            'phone_number' => '08099998888',
            'sex' => 'M'
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
            'phone_number' => '08012121212',
            'sex' => 'F',
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
     * Test patient search functionality in PatientService
     */
    public function testPatientServiceSearch()
    {
        // Create test patients
        $this->createTestPatient([
            'first_name' => 'SearchTest',
            'last_name' => 'Alpha',
            'sex' => 'M',
            'age' => 20
        ]);
        
        $this->createTestPatient([
            'first_name' => 'SearchTest',
            'last_name' => 'Beta',
            'sex' => 'F',
            'age' => 30
        ]);
        
        $this->createTestPatient([
            'first_name' => 'OtherTest',
            'last_name' => 'Gamma',
            'sex' => 'M',
            'age' => 40
        ]);
        
        // Search by first_name
        $results = PatientService::searchPatients(['first_name' => 'SearchTest']);
        $this->assertEquals(2, $results['meta']['total']);
        
        // Search by sex
        $results = PatientService::searchPatients(['sex' => 'F']);
        $this->assertGreaterThanOrEqual(1, $results['meta']['total']);
        
        // Search by age range
        $results = PatientService::searchPatients([
            'age_min' => 25,
            'age_max' => 35
        ]);
        
        $this->assertGreaterThanOrEqual(1, $results['meta']['total']);
        
        // Verify all returned patients are within the age range
        foreach ($results['patients']->items as $patient) {
            $this->assertGreaterThanOrEqual(25, $patient->age);
            $this->assertLessThanOrEqual(35, $patient->age);
        }
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
        $patient_id = $patient->id;
        
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
