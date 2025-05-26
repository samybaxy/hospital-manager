<?php
/**
 * Mock implementation of Appointment REST API for testing
 * 
 * This file provides test-specific implementations of the appointment API
 * functionality without relying on the full WPMVC framework.
 */

namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Appointment REST API class for tests
 */
class AppointmentMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register appointment REST API routes for testing
     */
    public static function register_routes() 
    {
        // GET and POST /appointments
        register_rest_route(self::$namespace, '/appointments', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getAppointments'],
                'permission_callback' => [self::class, 'checkDoctorPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createAppointment'],
                'permission_callback' => [self::class, 'checkPatientPermission'],
            ]
        ]);

        // Individual appointment routes
        register_rest_route(self::$namespace, '/appointments/(?P<ID>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getAppointment'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateAppointment'],
                'permission_callback' => [self::class, 'checkPermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteAppointment'],
                'permission_callback' => [self::class, 'checkPermission'],
            ]
        ]);

        // Appointment availability route
        register_rest_route(self::$namespace, '/appointments/availability', [
            'methods' => 'GET',
            'callback' => [self::class, 'getAvailability'],
            'permission_callback' => '__return_true', // Public endpoint
        ]);
    }

    /**
     * Check if user has doctor role
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkDoctorPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('doctor', $user->roles) || in_array('administrator', $user->roles);
    }
    
    /**
     * Check if user has patient role
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkPatientPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        return in_array('patient', $user->roles) || in_array('administrator', $user->roles);
    }
    
    /**
     * Check permissions for various appointment actions
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
        
        // Doctor can view and update appointments
        if (in_array('doctor', $roles)) {
            if ($method === 'GET' || $method === 'PUT') {
                return true;
            }
        }
        
        // Patient can view and update their own appointments
        if (in_array('patient', $roles)) {
            if ($method === 'GET' && isset($request['ID'])) {
                global $wpdb;
                $table = $wpdb->prefix . 'hm_appointments';
                
                // Get the appointment record being requested
                $appointment = $wpdb->get_row($wpdb->prepare(
                    "SELECT a.* FROM $table a 
                    INNER JOIN {$wpdb->prefix}hm_patients p ON a.patient_id = p.ID
                    WHERE a.ID = %d",
                    $request['ID']
                ));
                
                // Get the patient associated with current user
                $patient_table = $wpdb->prefix . 'hm_patients';
                $patient = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $patient_table WHERE user_id = %d",
                    get_current_user_id()
                ));
                
                // Patients can only access their own appointments
                if ($appointment && $patient && $appointment->patient_id == $patient->ID) {
                    return true;
                }
                
                return false;
            }
        }
        
        // Receptionists can access all appointments
        if (in_array('receptionist', $roles)) {
            return true;
        }
        
        // Default: deny access
        return false;
    }

    /**
     * Get all appointments
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getAppointments($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        // Get user role
        $user = wp_get_current_user();
        $roles = $user->roles;
        
        // Build base query
        $sql = "SELECT a.* FROM $table a";
        $params = [];
        
        // If user is a doctor, only show their appointments
        if (in_array('doctor', $roles) && !in_array('administrator', $roles)) {
            // Get the doctor ID for current user
            $doctor_table = $wpdb->prefix . 'hm_doctors';
            $doctor = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $doctor_table WHERE user_id = %d",
                get_current_user_id()
            ));
            
            if ($doctor) {
                $sql .= " WHERE a.doctor_id = %d";
                $params[] = $doctor->ID;
            }
        }
        
        // If user is a patient, only show their appointments
        if (in_array('patient', $roles) && !in_array('administrator', $roles)) {
            // Get the patient ID for current user
            $patient_table = $wpdb->prefix . 'hm_patients';
            $patient = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $patient_table WHERE user_id = %d",
                get_current_user_id()
            ));
            
            if ($patient) {
                $sql .= " WHERE a.patient_id = %d";
                $params[] = $patient->ID;
            }
        }
        
        // Add filters from request if any
        $filters = $request->get_params();
        if (!empty($filters['doctor_id'])) {
            $sql .= (strpos($sql, 'WHERE') !== false) ? " AND" : " WHERE";
            $sql .= " a.doctor_id = %d";
            $params[] = $filters['doctor_id'];
        }
        
        if (!empty($filters['patient_id'])) {
            $sql .= (strpos($sql, 'WHERE') !== false) ? " AND" : " WHERE";
            $sql .= " a.patient_id = %d";
            $params[] = $filters['patient_id'];
        }
        
        if (!empty($filters['date'])) {
            $sql .= (strpos($sql, 'WHERE') !== false) ? " AND" : " WHERE";
            $sql .= " a.appointment_date = %s";
            $params[] = $filters['date'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= (strpos($sql, 'WHERE') !== false) ? " AND" : " WHERE";
            $sql .= " a.status = %s";
            $params[] = $filters['status'];
        }
        
        // Add ordering
        $sql .= " ORDER BY a.appointment_date ASC, a.appointment_time ASC";
        
        // Prepare and execute the query
        $prepared_sql = count($params) > 0 ? $wpdb->prepare($sql, $params) : $sql;
        $appointments = $wpdb->get_results($prepared_sql);
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'appointments' => $appointments
            ]
        ], 200);
    }
    
    /**
     * Get a single appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getAppointment($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        $ID = $request['ID'];
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ID = %d", $ID));
        
        if (!$appointment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $appointment
        ], 200);
    }
    
    /**
     * Create a new appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function createAppointment($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        $data = $request->get_params();
        
        // Basic validation
        if (empty($data['doctor_id']) || empty($data['appointment_date']) || empty($data['appointment_time'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing required fields'
            ], 400);
        }
        
        // If patient is creating the appointment, set the patient_id automatically
        if (in_array('patient', wp_get_current_user()->roles) && !array_key_exists('patient_id', $data)) {
            $patient_table = $wpdb->prefix . 'hm_patients';
            $patient = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $patient_table WHERE user_id = %d",
                get_current_user_id()
            ));
            
            if ($patient) {
                $data['patient_id'] = $patient->ID;
            } else {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'Could not find patient record for current user'
                ], 400);
            }
        }
        
        // Validate appointment date (must be in future)
        $appointment_date = strtotime($data['appointment_date']);
        $today = strtotime(date('Y-m-d'));
        
        if ($appointment_date < $today) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Appointment date cannot be in the past'
            ], 400);
        }
        
        // Check for conflicting appointments
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table 
             WHERE doctor_id = %d 
             AND appointment_date = %s 
             AND appointment_time = %s 
             AND status NOT IN ('cancelled', 'completed')",
            $data['doctor_id'], $data['appointment_date'], $data['appointment_time']
        ));
        
        if ($existing > 0) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'There is a scheduling conflict for this time slot'
            ], 409);
        }
        
        // Set default status if not provided
        if (!isset($data['status'])) {
            $data['status'] = 'scheduled';
        }
        
        // Set created_at and updated_at
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');
        
        // Insert the appointment
        $result = $wpdb->insert($table, $data);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create appointment: ' . $wpdb->last_error
            ], 500);
        }
        
        $appointment_id = $wpdb->insert_id;
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ID = %d", $appointment_id));
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $appointment
        ], 201);
    }
    
    /**
     * Update an appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function updateAppointment($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        $ID = $request['ID'];
        $data = $request->get_params();
        
        // Check if appointment exists
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ID = %d", $ID));
        
        if (!$appointment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
        }
        
        // Remove ID from data to prevent overwrite
        unset($data['ID']);
        
        // If updating date/time, check for conflicts
        if ((isset($data['appointment_date']) || isset($data['appointment_time'])) && 
            (!isset($data['status']) || $data['status'] != 'cancelled')) {
            
            $date = isset($data['appointment_date']) ? $data['appointment_date'] : $appointment->appointment_date;
            $time = isset($data['appointment_time']) ? $data['appointment_time'] : $appointment->appointment_time;
            
            // Check for conflicting appointments
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table 
                 WHERE doctor_id = %d 
                 AND appointment_date = %s 
                 AND appointment_time = %s 
                 AND ID != %d
                 AND status NOT IN ('cancelled', 'completed')",
                $appointment->doctor_id, $date, $time, $ID
            ));
            
            if ($existing > 0) {
                return new \WP_REST_Response([
                    'success' => false,
                    'message' => 'There is a scheduling conflict for this time slot'
                ], 409);
            }
        }
        
        // Set updated_at
        $data['updated_at'] = current_time('mysql');
        
        // Update the appointment
        $result = $wpdb->update($table, $data, ['ID' => $ID]);
        
        if ($result === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update appointment: ' . $wpdb->last_error
            ], 500);
        }
        
        $updated_appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ID = %d", $ID));
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $updated_appointment
        ], 200);
    }
    
    /**
     * Delete an appointment
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function deleteAppointment($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        $ID = $request['ID'];
        
        // Check if appointment exists
        $appointment = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE ID = %d", $ID));
        
        if (!$appointment) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Appointment not found'
            ], 404);
        }
        
        // Delete the appointment
        $result = $wpdb->delete($table, ['ID' => $ID]);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to delete appointment: ' . $wpdb->last_error
            ], 500);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Appointment deleted successfully'
        ], 200);
    }
    
    /**
     * Get availability for a doctor on a specific date
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getAvailability($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_appointments';
        
        $filters = $request->get_params();
        
        // Validate required parameters
        if (empty($filters['doctor_id']) || empty($filters['date'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Doctor ID and date are required'
            ], 400);
        }
        
        // Get all appointments for this doctor on this date
        $appointments = $wpdb->get_results($wpdb->prepare(
            "SELECT appointment_time FROM $table 
             WHERE doctor_id = %d 
             AND appointment_date = %s
             AND status NOT IN ('cancelled')",
            $filters['doctor_id'], $filters['date']
        ));
        
        // Create an array of booked time slots
        $booked_slots = [];
        foreach ($appointments as $appointment) {
            $booked_slots[] = $appointment->appointment_time;
        }
        
        // Generate all time slots from 8:00 to 17:00 with 30 minute intervals
        $available_slots = [];
        $start_time = strtotime('08:00');
        $end_time = strtotime('17:00');
        $interval = 30 * 60; // 30 minutes in seconds
        
        for ($time = $start_time; $time <= $end_time; $time += $interval) {
            $time_str = date('H:i:s', $time);
            $available_slots[] = [
                'time' => $time_str,
                'available' => !in_array($time_str, $booked_slots),
                'label' => date('g:i A', $time)
            ];
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => [
                'date' => $filters['date'],
                'doctor_id' => $filters['doctor_id'],
                'available_slots' => $available_slots
            ]
        ], 200);
    }
}
