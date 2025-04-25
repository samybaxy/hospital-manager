<?php
/**
 * Mock implementation of Patient REST API for testing
 * 
 * This file provides test-specific implementations of the patient API
 * functionality without relying on the full WPMVC framework.
 */

namespace HospitalManager\Tests\Mocks;

/**
 * Mock Patient REST API class for tests
 */
class PatientMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register patient REST API routes for testing
     */
    public static function register_routes() 
    {
        // GET and POST /patients
        register_rest_route(self::$namespace, '/patients', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getPatients'],
                'permission_callback' => [self::class, 'checkAdminDoctorPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createPatient'],
                'permission_callback' => [self::class, 'checkPermission'],
            ]
        ]);

        // Individual patient routes
        register_rest_route(self::$namespace, '/patients/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getPatient'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updatePatient'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deletePatient'],
                'permission_callback' => [self::class, 'checkAdminPermission'],
            ]
        ]);

        // Patient self-access route
        register_rest_route(self::$namespace, '/patients/me', [
            'methods' => 'GET',
            'callback' => [self::class, 'getPatientSelf'],
            'permission_callback' => [self::class, 'checkPatientSelfPermission'],
        ]);

        // Patient search route
        register_rest_route(self::$namespace, '/patients/search', [
            'methods' => 'GET',
            'callback' => [self::class, 'searchPatients'],
            'permission_callback' => [self::class, 'checkAdminDoctorPermission'],
        ]);
    }

    /**
     * Check if user has admin role
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkAdminPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('administrator', $user->roles);
    }
    
    /**
     * Check if user has admin or doctor role
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkAdminDoctorPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('administrator', $user->roles) || in_array('doctor', $user->roles);
    }
    
    /**
     * Check if the current user can createPatient, getPatient, updatePatient
     * based on user's role and capabilities.
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $roles = $user->roles;
        $method = $request->get_method();
        
        // Admin can do everything
        if (in_array('administrator', $roles)) {
            return true;
        }
        
        // Doctor and receptionist can create, get, and update patients
        if (in_array('doctor', $roles) || in_array('receptionist', $roles)) {
            return true;
        }
        
        // Patient permissions
        if (in_array('patient', $roles)) {
            // If this is a GET request to a specific patient
            if ($method === 'GET' && isset($request['id'])) {
                global $wpdb;
                $table = $wpdb->prefix . 'hm_patients';
                
                // Get the patient record being requested
                $patient = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d",
                    $request['id']
                ));
                
                // Patients can only access their own records
                if ($patient && $patient->user_id != get_current_user_id()) {
                    return false; // Not allowed to access other patients
                }
                
                return true; // Can access own patient record
            }
            
            // Patients cannot create new patients or update other patients
            return false;
        }
        
        // Default: deny access
        return false;
    }
    
    /**
     * Check if user is the patient or has permissions to view patient data
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkPatientSelfPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('patient', $user->roles);
    }

    /**
     * Get all patients
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getPatients($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $patients = $wpdb->get_results("SELECT * FROM $table");
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'patients' => $patients
            ]
        ], 200);
    }
    
    /**
     * Get a single patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getPatient($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $id = $request['id'];
        $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$patient) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $patient
        ], 200);
    }
    
    /**
     * Create a new patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function createPatient($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $data = $request->get_params();
        
        // Basic validation
        if (empty($data['first_name']) || empty($data['last_name'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing required fields'
            ], 400);
        }
        
        $result = $wpdb->insert($table, $data);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create patient'
            ], 500);
        }
        
        $patient_id = $wpdb->insert_id;
        $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $patient_id));
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $patient
        ], 201);
    }
    
    /**
     * Update a patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function updatePatient($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $id = $request['id'];
        $data = $request->get_params();
        
        // Check if patient exists
        $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$patient) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }
        
        // Remove ID from data to prevent overwrite
        unset($data['id']);
        
        $result = $wpdb->update($table, $data, ['id' => $id]);
        
        if ($result === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update patient'
            ], 500);
        }
        
        $updated_patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $updated_patient
        ], 200);
    }
    
    /**
     * Delete a patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function deletePatient($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $id = $request['id'];
        
        // Check if patient exists
        $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$patient) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Patient not found'
            ], 404);
        }
        
        $result = $wpdb->delete($table, ['id' => $id]);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to delete patient'
            ], 500);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Patient deleted successfully'
        ], 200);
    }
    
    /**
     * Get current patient's own record
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getPatientSelf($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $user_id = get_current_user_id();
        $patient = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE user_id = %d", $user_id));
        
        if (!$patient) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Patient record not found'
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $patient
        ], 200);
    }
    
    /**
     * Search for patients
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function searchPatients($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        $query = $request->get_param('query');
        
        if (empty($query)) {
            return new \WP_REST_Response([
                'success' => true,
                'data' => []
            ], 200);
        }
        
        $search = '%' . $wpdb->esc_like($query) . '%';
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE 
            first_name LIKE %s OR 
            last_name LIKE %s OR 
            phone LIKE %s
            LIMIT 20",
            $search, $search, $search
        );
        
        $patients = $wpdb->get_results($sql);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $patients
        ], 200);
    }
}
