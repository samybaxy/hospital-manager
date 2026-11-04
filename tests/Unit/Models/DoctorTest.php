<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Doctor;

class DoctorTest extends TestCase
{

    /**
     * Test finding a doctor by ID
     */
    public function testFindDoctor()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        
        // Find the doctor by ID
        $found_doctor = Doctor::find($test_doctor->ID);
        
        $this->assertInstanceOf(Doctor::class, $found_doctor);
        $this->assertEquals($test_doctor->ID, $found_doctor->ID);
        $this->assertEquals($test_doctor->first_name, $found_doctor->first_name);
        $this->assertEquals($test_doctor->last_name, $found_doctor->last_name);
    }



    /**
     * Test updating a doctor
     */
    public function testUpdate()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        
        // Store the original ID and get original data
        $doctor_id = $test_doctor->ID;
        
        // Set a new phone number
        $new_phone = '07011223344';
        // Use proper setter method instead of directly accessing protected attributes array
        $test_doctor->phone = $new_phone;
        
        // Save the changes
        $result = $test_doctor->save();
        
        // Verify save was successful
        $this->assertTrue($result, "The save operation should return true");
        
        // Get a fresh instance from the database to verify the update
        global $wpdb;
        $table = $wpdb->prefix . 'hm_doctors';
        
        // Use direct SQL query to check the database
        $sql = $wpdb->prepare("SELECT phone FROM $table WHERE ID = %d", $doctor_id);
        $db_phone = $wpdb->get_var($sql);
        
        // Verify the phone was updated in the database
        $this->assertEquals($new_phone, $db_phone, "The phone number should be updated in the database");
    }

    /**
     * Test deleting a doctor
     */
    public function testDelete()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        $doctor_id = $test_doctor->ID;
        
        // Delete the doctor
        $test_doctor->delete();
        
        // Try to find the deleted doctor
        $deleted_doctor = Doctor::find($doctor_id);
        
        $this->assertNull($deleted_doctor);
    }
}
