<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\Doctor;

class DoctorTest extends TestCase
{
    /**
     * Test doctor creation
     */
    public function testCreateDoctor()
    {
        $data = [
            'user_id' => 1,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone_number' => '08098765432',
            'photo' => 'doctor-jane.jpg'
        ];

        $doctor = Doctor::create($data);

        $this->assertInstanceOf(Doctor::class, $doctor);
        $this->assertEquals('Jane', $doctor->first_name);
        $this->assertEquals('Smith', $doctor->last_name);
        $this->assertEquals('08098765432', $doctor->phone_number);
        $this->assertEquals('doctor-jane.jpg', $doctor->photo);
    }

    /**
     * Test finding a doctor by ID
     */
    public function testFindDoctor()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        
        // Find the doctor by ID
        $found_doctor = Doctor::find($test_doctor->id);
        
        $this->assertInstanceOf(Doctor::class, $found_doctor);
        $this->assertEquals($test_doctor->id, $found_doctor->id);
        $this->assertEquals($test_doctor->first_name, $found_doctor->first_name);
        $this->assertEquals($test_doctor->last_name, $found_doctor->last_name);
    }

    /**
     * Test finding doctors by a condition
     */
    public function testWhere()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        
        // Get doctors with the same first name
        $doctors = Doctor::where('first_name', $test_doctor->first_name);
        
        $this->assertIsArray($doctors);
        $this->assertNotEmpty($doctors);
        $this->assertInstanceOf(Doctor::class, $doctors[0]);
        $this->assertEquals($test_doctor->first_name, $doctors[0]->first_name);
    }

    /**
     * Test finding a doctor by user_id
     */
    public function testFindByUserId()
    {
        // Create a test doctor with a known user_id
        $user_id = 999;
        $test_doctor = $this->createTestDoctor([
            'user_id' => $user_id
        ]);
        
        // Find by user_id
        $doctors = Doctor::where('user_id', $user_id);
        
        $this->assertIsArray($doctors);
        $this->assertNotEmpty($doctors);
        $this->assertEquals($user_id, $doctors[0]->user_id);
    }

    /**
     * Test updating a doctor
     */
    public function testUpdate()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        
        // Update data
        $test_doctor->phone_number = '07011223344';
        $test_doctor->save();
        
        // Retrieve the doctor again to verify changes were saved
        $updated_doctor = Doctor::find($test_doctor->id);
        
        $this->assertEquals('07011223344', $updated_doctor->phone_number);
    }

    /**
     * Test deleting a doctor
     */
    public function testDelete()
    {
        // Create a test doctor
        $test_doctor = $this->createTestDoctor();
        $doctor_id = $test_doctor->id;
        
        // Delete the doctor
        $test_doctor->delete();
        
        // Try to find the deleted doctor
        $deleted_doctor = Doctor::find($doctor_id);
        
        $this->assertNull($deleted_doctor);
    }
}
