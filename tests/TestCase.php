<?php

namespace HospitalManager\Tests;

use HospitalManager\Tests\Helpers\Debugger;

/**
 * Base TestCase for Hospital Manager plugin tests
 */
class TestCase extends \WP_UnitTestCase
{
    /**
     * Set up before each test
     */
    public function setUp(): void
    {
        parent::setUp();
        
        // Initialize database tables for tests
        $this->initTestDatabase();
        
        // Common setup code for all tests
    }
    
    /**
     * Initialize test database tables
     */
    protected function initTestDatabase()
    {
        global $wpdb;
        
        // Check if tables need to be created
        $table_name = $wpdb->prefix . 'hm_patients';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        
        if (!$table_exists) {
            // Include the database migration file
            $migration_file = dirname(dirname(__FILE__)) . '/database/migrations/create_hospital_tables.php';
            
            if (file_exists($migration_file)) {
                require_once $migration_file;
                // Run the migration to create tables
                \CreateHospitalTables::up();
            } else {
                $this->markTestSkipped("Database migration file not found: $migration_file");
            }
        }
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        // Common teardown code for all tests
        parent::tearDown();
    }

    /**
     * Create a test user with a specific role
     *
     * @param string $role The user role
     * @return int User ID
     */
    protected function createUserWithRole(string $role): int
    {
        $user_id = $this->factory->user->create([
            'role' => $role,
        ]);
        return $user_id;
    }

    /**
     * Create a test patient
     *
     * @param array $overrides Override default patient data
     * @return \HospitalManager\Models\Patient|null
     */
    protected function createTestPatient(array $overrides = [])
    {
        $default_data = [
            'first_name' => 'Test',
            'last_name' => 'Patient',
            'phone' => '08012345678',
            'gender' => 'Male',
            'age' => 30,
            'bio_data' => json_encode([
                'blood_group' => 'O+',
                'genotype' => 'AA',
                'height' => '170',
                'weight' => '70',
                'allergies' => 'None',
                'chronic_conditions' => 'None',
                'current_medications' => 'None',
                'emergency_contact' => '09087654321',
                'notes' => 'Test patient for PHPUnit tests'
            ]),
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Log the data being used for patient creation
            \HospitalManager\Tests\Helpers\Debugger::log('Creating test patient with data:', $data);
            
            // Create the patient
            $patient = \HospitalManager\Models\Patient::create($data);
            
            \HospitalManager\Tests\Helpers\Debugger::log('Patient created successfully:', $patient);
            return $patient;
        } catch (\Exception $e) {
            \HospitalManager\Tests\Helpers\Debugger::log('Error creating patient: ' . $e->getMessage());
            \HospitalManager\Tests\Helpers\Debugger::log('Error trace:', $e->getTraceAsString());
            $this->fail('Failed to create test patient: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a test doctor
     *
     * @param array $overrides Override default doctor data
     * @return \HospitalManager\Models\Doctor|null
     */
    protected function createTestDoctor(array $overrides = [])
    {
        $default_data = [
            'first_name' => 'Test',
            'last_name' => 'Doctor',
            'phone' => '08012345679',
            'specialization' => 'General Practice',
            'status' => 'active',
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return \HospitalManager\Models\Doctor::create($data);
        } catch (\Exception $e) {
            $this->fail('Failed to create test doctor: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a test appointment
     *
     * @param int $patient_id Patient ID
     * @param int $doctor_id Doctor ID
     * @param array $overrides Override default appointment data
     * @return \HospitalManager\Models\Appointment|null
     */
    protected function createTestAppointment(int $patient_id, int $doctor_id, array $overrides = [])
    {
        $default_data = [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => date('Y-m-d'),
            'appointment_time' => '10:00:00',
            'reason' => 'Test appointment',
            'status' => 'scheduled',
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return \HospitalManager\Models\Appointment::create($data);
        } catch (\Exception $e) {
            $this->fail('Failed to create test appointment: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a test medical report
     * 
     * @param int $patient_id Patient ID
     * @param array $overrides Override default medical report data
     * @return \HospitalManager\Models\MedicalReport|null
     */
    protected function createTestMedicalReport(int $patient_id, array $overrides = [])
    {
        $default_data = [
            'patient_id' => $patient_id,
            'diagnosis' => 'Test diagnosis',
            'treatment' => 'Test treatment',
            'notes' => 'Test medical report notes',
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return \HospitalManager\Models\MedicalReport::create($data);
        } catch (\Exception $e) {
            $this->fail('Failed to create test medical report: ' . $e->getMessage());
            return null;
        }
    }
}
