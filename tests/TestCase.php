<?php

namespace HospitalManager\Tests;

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
        // Common setup code for all tests
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
            'phone_number' => '08012345678',
            'sex' => 'M',
            'age' => 30,
            'bio_data' => 'Test patient for PHPUnit tests',
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return \HospitalManager\Models\Patient::create($data);
        } catch (\Exception $e) {
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
            'phone_number' => '08012345679',
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
