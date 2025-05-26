<?php

namespace HospitalManager\Tests\Unit\Models;

use HospitalManager\Tests\TestCase;
use HospitalManager\Models\BaseModel;
use HospitalManager\Models\Patient;

class BaseModelTest extends TestCase
{
    /**
     * Test that models properly extend BaseModel
     */
    public function testModelsExtendBaseModel()
    {
        $patient = $this->createTestPatient();
        
        $this->assertInstanceOf(BaseModel::class, $patient);
    }
    
    /**
     * Test FindTrait functionality inherited from BaseModel
     */
    public function testFindTraitFunctionality()
    {
        // Create a test patient
        $patient = $this->createTestPatient();
        
        // Test that find() works (inherited from BaseModel)
        $found_patient = Patient::find($patient->ID);
        
        $this->assertInstanceOf(Patient::class, $found_patient);
        $this->assertEquals($patient->ID, $found_patient->ID);
    }
    
    /**
     * Test primary key is set correctly
     */
    public function testPrimaryKeyIsSet()
    {
        $reflection = new \ReflectionClass(Patient::class);
        $property = $reflection->getProperty('primaryKey');
        $property->setAccessible(true);
        
        $patient = $this->createTestPatient();
        
        $this->assertEquals('ID', $property->getValue($patient));
    }
    
    /**
     * Test abstract createTable method pattern
     */
    public function testCreateTableImplementation()
    {
        // Ensure all model classes that extend BaseModel implement createTable()
        $models = [
            Patient::class,
            \HospitalManager\Models\Doctor::class,
            \HospitalManager\Models\Appointment::class,
            \HospitalManager\Models\Visitation::class,
            \HospitalManager\Models\LabInvestigation::class,
        ];
        
        foreach ($models as $modelClass) {
            $this->assertTrue(
                method_exists($modelClass, 'createTable'),
                "Model class {$modelClass} should implement createTable() method from BaseModel"
            );
        }
    }
    
    /**
     * Test model attributes and fillable properties
     */
    public function testModelAttributes()
    {
        $patient = $this->createTestPatient([
            'first_name' => 'John',
            'last_name' => 'Doe'
        ]);
        
        // Test attribute access (should work because of BaseModel's foundation)
        $this->assertEquals('John', $patient->first_name);
        $this->assertEquals('Doe', $patient->last_name);
        
        // Test updating attributes
        $patient->first_name = 'Jane';
        $patient->save();
        
        $updated_patient = Patient::find($patient->ID);
        $this->assertEquals('Jane', $updated_patient->first_name);
    }
}
