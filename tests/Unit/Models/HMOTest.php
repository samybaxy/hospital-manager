<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\HMO;
use HospitalManager\Models\Patient;

class HMOTest extends TestCase
{
    /**
     * Test HMO creation
     */
    public function testCreateHMO()
    {
        $data = [
            'name' => 'National Health Insurance'
        ];

        $hmo = HMO::create($data);

        $this->assertInstanceOf(HMO::class, $hmo);
        $this->assertEquals('National Health Insurance', $hmo->name);
    }

    /**
     * Test finding an HMO by ID
     */
    public function testFindHMO()
    {
        // Create a test HMO
        $hmo = $this->createTestHMO([
            'name' => 'Premium Health Partners'
        ]);
        
        // Find the HMO by ID
        $found_hmo = HMO::find($hmo->id);
        
        $this->assertInstanceOf(HMO::class, $found_hmo);
        $this->assertEquals($hmo->id, $found_hmo->id);
        $this->assertEquals('Premium Health Partners', $found_hmo->name);
    }

    /**
     * Test patient relationship
     */
    public function testPatientsRelationship()
    {
        // Create a test HMO
        $hmo = $this->createTestHMO([
            'name' => 'Federal Health Network'
        ]);
        
        // Create patients linked to this HMO
        $patient1 = $this->createTestPatient([
            'hmo_id' => $hmo->id,
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        $patient2 = $this->createTestPatient([
            'hmo_id' => $hmo->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith'
        ]);
        
        // Get the related patients
        $patients = $hmo->patients();
        
        $this->assertIsArray($patients);
        $this->assertCount(2, $patients);
        $this->assertInstanceOf(Patient::class, $patients[0]);
        
        // Verify patient data
        $patient_ids = [$patient1->id, $patient2->id];
        $found_ids = [$patients[0]->id, $patients[1]->id];
        sort($patient_ids);
        sort($found_ids);
        $this->assertEquals($patient_ids, $found_ids);
    }

    /**
     * Test updating an HMO
     */
    public function testUpdateHMO()
    {
        // Create a test HMO
        $hmo = $this->createTestHMO([
            'name' => 'Old HMO Name'
        ]);
        
        // Update the HMO
        $hmo->name = 'Updated HMO Name';
        $hmo->save();
        
        // Retrieve the HMO again
        $updated_hmo = HMO::find($hmo->id);
        
        $this->assertEquals('Updated HMO Name', $updated_hmo->name);
    }

    /**
     * Test deleting an HMO
     */
    public function testDeleteHMO()
    {
        // Create a test HMO
        $hmo = $this->createTestHMO([
            'name' => 'Temporary HMO'
        ]);
        
        $hmo_id = $hmo->id;
        
        // Delete the HMO
        $hmo->delete();
        
        // Try to find the deleted HMO
        $deleted_hmo = HMO::find($hmo_id);
        
        $this->assertNull($deleted_hmo);
    }
}
