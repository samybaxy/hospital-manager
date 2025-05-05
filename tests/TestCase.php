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
        
        // Register custom roles for testing
        $this->registerCustomRoles();
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
     * Register custom roles needed for testing
     */
    protected function registerCustomRoles(): void
    {
        // Doctor role
        if (!get_role('doctor')) {
            add_role(
                'doctor',
                'Doctor',
                [
                    'read' => true,
                    'view_patients' => true,
                    'edit_patients' => true,
                    'schedule_appointments' => true,
                    'create_medical_reports' => true,
                    'add_visitation' => true,
                    'edit_visitation' => true,
                    'manage_medical_reports' => true
                ]
            );
        }
        
        // Patient role
        if (!get_role('patient')) {
            add_role(
                'patient',
                'Patient',
                [
                    'read' => true,
                    'view_own_records' => true,
                    // Add other capabilities as needed
                ]
            );
        }
        
        // Desk Officer Role
        if (!get_role('desk_officer')) {
            add_role(
                'desk_officer',
                'Desk Officer',
                [
                    'read' => true,
                    'create_patients' => true,
                    'view_patients' => true,
                    'edit_patients' => true,
                    'view_audit_log' => true,
                    'schedule_appointments' => true
                ]
            );
        }
        
        // Lab Tech role
        if (!get_role('lab_tech')) {
            add_role(
                'lab_tech',
                'Lab Technician',
                [
                    'read' => true,
                    'view_patients' => true,
                    'manage_medical_reports' => true,
                    // Add other capabilities as needed
                ]
            );
        }
    }

    /**
     * Tear down after each test
     */
    public function tearDown(): void
    {
        // Common teardown code for all tests
        remove_role('doctor');
        remove_role('patient');
        remove_role('desk_officer');
        remove_role('lab_tech');
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
        // First create the user
        $user_id = $this->factory->user->create();

        // Then explicitly set the role
        $user = new \WP_User($user_id);
        $user->set_role($role);

        // Force a capability refresh, which can be important in test environment
        $user = new \WP_User($user_id);

        // Verify role was set correctly (for debugging)
        if (!in_array($role, $user->roles)) {
            error_log("Warning: Failed to set role '{$role}' for user {$user_id}");
        }
        
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
            'user_id' => 0,
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
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Log the data being used for patient creation
            Debugger::log('Creating test patient with data:', $data);
            
            // Ensure the gender is properly set (this seems to be a persistent issue)
            if (!empty($data['gender']) && ($data['gender'] === 'F' || $data['gender'] === 'M')) {
                // Force the gender value to be exactly as specified
                global $wpdb;
                $patient = \HospitalManager\Models\Patient::create($data);
                $table = (new \HospitalManager\Models\Patient)->getTable();
                
                // Update the gender value directly
                $wpdb->update(
                    $table,
                    ['gender' => $data['gender']],
                    ['id' => $patient->id],
                    ['%s'],
                    ['%d']
                );
                
                // Re-fetch to get the updated gender
                $patient = \HospitalManager\Models\Patient::find($patient->id);
            } else {
                // Create normally
                $patient = \HospitalManager\Models\Patient::create($data);
            }
            
            Debugger::log('Patient created successfully:', $patient);
            return $patient;
        } catch (\Exception $e) {
            Debugger::log('Error creating patient: ' . $e->getMessage());
            Debugger::log('Error trace:', $e->getTraceAsString());
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
            'user_id' => isset($overrides['user_id']) ? $overrides['user_id'] : 0,
            'first_name' => 'Test',
            'last_name' => 'Doctor',
            'phone' => '08012345679',
            'photo' => null,
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Log the data being used for doctor creation
            Debugger::log('Creating test doctor with data:', $data);
            
            // Create the doctor
            $doctor = \HospitalManager\Models\Doctor::create($data);
            
            Debugger::log('Doctor created successfully:', $doctor);
            return $doctor;
        } catch (\Exception $e) {
            Debugger::log('Error creating doctor: ' . $e->getMessage());
            Debugger::log('Error trace:', $e->getTraceAsString());
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
    protected function createTestAppointment($patient_id, $doctor_id, array $overrides = [])
    {
        $default_data = [
            'patient_id' => $patient_id,
            'doctor_id' => $doctor_id,
            'appointment_date' => date('Y-m-d', strtotime('+1 day')),
            'appointment_time' => '10:00:00',
            'reason' => 'Test appointment',
            'status' => 'scheduled',
            'notes' => 'Created for testing purposes',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Log the data being used for appointment creation
            error_log('Creating test appointment with data: ' . print_r($data, true));
            
            // Create the appointment
            $appointment = \HospitalManager\Models\Appointment::create($data);
            
            if ($appointment) {
                error_log('Appointment created successfully with ID: ' . (isset($appointment->id) ? $appointment->id : 'No ID found'));
            } else {
                error_log('Failed to create appointment - returned null');
            }
            
            return $appointment;
        } catch (\Exception $e) {
            error_log('Error creating appointment: ' . $e->getMessage());
            error_log('Error trace: ' . $e->getTraceAsString());
            return null;
        }
    }

    /**
     * Create a test medical report
     * 
     * @param array $overrides Override default medical report data
     * @return \HospitalManager\Models\MedicalReport|null
     */
    protected function createTestMedicalReport(array $overrides = [])
    {
        $default_data = [
            'patient_id' => isset($overrides['patient_id']) ? $overrides['patient_id'] : 0,
            'doctor_id' => isset($overrides['doctor_id']) ? $overrides['doctor_id'] : 0,
            'visitation_id' => isset($overrides['visitation_id']) ? $overrides['visitation_id'] : 0,
            'report_content' => 'Test report content',
            'diagnosis' => 'Test diagnosis',
            'treatment' => 'Test treatment',
            'status' => 'pending',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            return \HospitalManager\Models\MedicalReport::create($data);
        } catch (\Exception $e) {
            $this->fail('Failed to create test medical report: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Create a test audit log entry
     *
     * @param array $overrides Override default audit log data
     * @return \HospitalManager\Models\AuditLog|null
     */
    protected function createTestAuditLog(array $overrides = [])
    {
        // Get user ID from overrides or create a test user
        $user_id = isset($overrides['user_id']) ? $overrides['user_id'] : $this->createUserWithRole('administrator');
        
        $default_data = [
            'user_id' => $user_id,
            'action' => 'test_action',
            'entity_type' => 'test_entity',
            'entity_id' => 1,
            'details' => json_encode(['test' => 'data']),
            'changes' => json_encode(['field' => 'value']),
            'created_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Create the audit log entry using the model's create method
            return \HospitalManager\Models\AuditLog::create($data);
        } catch (\Exception $e) {
            error_log('Error creating audit log: ' . $e->getMessage());
            if ($e->getMessage()) {
                error_log('Database error: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Create a test HMO
     *
     * @param array $overrides Override default HMO data
     * @return \HospitalManager\Models\HMO|null
     */
    protected function createTestHMO(array $overrides = [])
    {
        $default_data = [
            'name' => 'Test HMO',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            // Create the HMO using the model's create method
            return \HospitalManager\Models\HMO::create($data);
        } catch (\Exception $e) {
            error_log('Error creating HMO: ' . $e->getMessage());
            if ($e->getMessage()) {
                error_log('Database error: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Create a test visitation
     *
     * @param array $overrides Override default visitation data
     * @return \HospitalManager\Models\Visitation|null
     */
    protected function createTestVisitation(array $overrides = [])
    {
        $default_data = [
            'patient_id' => isset($overrides['patient_id']) ? $overrides['patient_id'] : 0,
            'doctor_id' => isset($overrides['doctor_id']) ? $overrides['doctor_id'] : 0,
            'date' => date('Y-m-d'),
            'time' => '10:00:00',
            'medical_history' => 'Test medical history',
            'diagnosis' => 'Test diagnosis',
            'treatment' => 'Test treatment',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_visitations';
            
            // Insert the record
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            $data['id'] = $wpdb->insert_id;
            $data['ID'] = $data['id']; // Add uppercase ID for compatibility
            
            return new \HospitalManager\Models\Visitation($data);
        } catch (\Exception $e) {
            error_log('Error creating visitation: ' . $e->getMessage());
            if ($e->getMessage()) {
                error_log('Database error: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Create a test lab investigation
     *
     * @param array $overrides Override default lab investigation data
     * @return \HospitalManager\Models\LabInvestigation|null
     */
    protected function createTestLabInvestigation(array $overrides = [])
    {
        $default_data = [
            'visitation_id' => isset($overrides['visitation_id']) ? $overrides['visitation_id'] : 0,
            'patient_id' => isset($overrides['patient_id']) ? $overrides['patient_id'] : 0,
            'doctor_id' => isset($overrides['doctor_id']) ? $overrides['doctor_id'] : 0,
            'lab_tech_id' => isset($overrides['lab_tech_id']) ? $overrides['lab_tech_id'] : 0,
            'test_type' => 'Blood Test',
            'status' => 'pending',
            'notes' => 'Test lab investigation',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_lab_investigations';
            
            // Insert the record
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            $data['id'] = $wpdb->insert_id;
            $data['ID'] = $data['id']; // Add uppercase ID for compatibility
            
            return new \HospitalManager\Models\LabInvestigation($data);
        } catch (\Exception $e) {
            error_log('Error creating lab investigation: ' . $e->getMessage());
            if ($e->getMessage()) {
                error_log('Database error: ' . $e->getMessage());
            }
            return null;
        }
    }

    /**
     * Create a test notification
     *
     * @param array $overrides Override default notification data
     * @return \HospitalManager\Models\Notification|null
     */
    protected function createTestNotification(array $overrides = [])
    {
        $default_data = [
            'user_id' => isset($overrides['user_id']) ? $overrides['user_id'] : 0,
            'type' => 'test',
            'title' => 'Test Notification',
            'message' => 'This is a test notification',
            'read' => 0,
            'created_at' => current_time('mysql')
        ];

        $data = array_merge($default_data, $overrides);
        
        try {
            global $wpdb;
            $table = $wpdb->prefix . 'hm_notifications';
            
            // Insert the record
            $result = $wpdb->insert(
                $table,
                $data,
                array_map(function($field) {
                    return is_numeric($field) ? '%d' : '%s';
                }, $data)
            );
            
            if ($result === false) {
                throw new \Exception($wpdb->last_error);
            }
            
            $data['id'] = $wpdb->insert_id;
            $data['ID'] = $data['id']; // Add uppercase ID for compatibility
            
            return new \HospitalManager\Models\Notification($data);
        } catch (\Exception $e) {
            error_log('Error creating notification: ' . $e->getMessage());
            if ($e->getMessage()) {
                error_log('Database error: ' . $e->getMessage());
            }
            return null;
        }
    }
}
