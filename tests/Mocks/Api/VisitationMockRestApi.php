<?php
/**
 * Mock implementation of Visitation REST API for testing
 * 
 * This file provides test-specific implementations of the visitation API
 * functionality without relying on the full WPMVC framework.
 */

namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Visitation REST API class for tests
 */
class VisitationMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register visitation REST API routes for testing
     */
    public static function register_routes() 
    {
        // GET and POST /visitations
        register_rest_route(self::$namespace, '/visitations', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getVisitations'],
                'permission_callback' => [self::class, 'checkAccessPermission'],
            ],
            [
                'methods' => 'POST',
                'callback' => [self::class, 'createVisitation'],
                'permission_callback' => [self::class, 'checkCreatePermission'],
            ]
        ]);

        // Individual visitation routes
        register_rest_route(self::$namespace, '/visitations/(?P<id>\d+)', [
            [
                'methods' => 'GET',
                'callback' => [self::class, 'getVisitation'],
                'permission_callback' => [self::class, 'checkAccessPermission'],
            ],
            [
                'methods' => 'PUT',
                'callback' => [self::class, 'updateVisitation'],
                'permission_callback' => [self::class, 'checkCreatePermission'],
            ],
            [
                'methods' => 'DELETE',
                'callback' => [self::class, 'deleteVisitation'],
                'permission_callback' => [self::class, 'checkAdminPermission'],
            ]
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
     * Check if user has admin, doctor, or nurse role
     * (Only these roles can create/update visitations)
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkCreatePermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $allowed_roles = ['administrator', 'doctor', 'nurse', 'receptionist'];
        
        foreach ($allowed_roles as $role) {
            if (in_array($role, $user->roles)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if user has permission to access visitation data
     * - Admins, doctors, nurses, and receptionists can access all visitations
     * - Patients can only access their own visitations
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkAccessPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $roles = $user->roles;
        
        // Admin, doctor, can access all visitations
        $staff_roles = ['administrator', 'doctor', 'nurse'];
        foreach ($staff_roles as $role) {
            if (in_array($role, $roles)) {
                return true;
            }
        }
        
        // If user is a patient, they can only access their own visitations
        if (in_array('patient', $roles)) {
            // For individual visitation request, check if it belongs to this patient
            if (isset($request['id'])) {
                global $wpdb;
                $table = $wpdb->prefix . 'hm_visitations';
                $patient_id = self::getPatientIdForUser(get_current_user_id());
                
                if (!$patient_id) {
                    return false;
                }
                
                $visitation = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE id = %d AND patient_id = %d",
                    $request['id'], $patient_id
                ));
                
                return $visitation !== null;
            }
            
            // For list request, we'll filter results in the getVisitations method
            return true;
        }
        
        // Default deny access
        return false;
    }

    /**
     * Get patient ID for a user
     * 
     * @param int $user_id
     * @return int|false
     */
    protected static function getPatientIdForUser($user_id) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_patients';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d",
            $user_id
        ));
    }

    /**
     * Get all visitations
     * For patients: only return their own visitations
     * For staff: return all visitations or filtered by request parameters
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getVisitations($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $user = wp_get_current_user();
        $roles = $user->roles;
        
        // Start building the query
        $query = "SELECT * FROM $table WHERE 1=1";
        $args = [];
        
        // If user is a patient, only show their visitations
        if (in_array('patient', $roles)) {
            $patient_id = self::getPatientIdForUser(get_current_user_id());
            if (!$patient_id) {
                return new \WP_REST_Response([], 200);
            }
            
            $query .= " AND patient_id = %d";
            $args[] = $patient_id;
        } else {
            // Handle filters if user is staff
            // Filter by patient_id if provided
            if (!empty($request['patient_id'])) {
                $query .= " AND patient_id = %d";
                $args[] = $request['patient_id'];
            }
            
            // Filter by doctor_id if provided
            if (!empty($request['doctor_id'])) {
                $query .= " AND doctor_id = %d";
                $args[] = $request['doctor_id'];
            }
            
            // Filter by date range if provided
            if (!empty($request['start_date'])) {
                $query .= " AND date >= %s";
                $args[] = $request['start_date'];
            }
            
            if (!empty($request['end_date'])) {
                $query .= " AND date <= %s";
                $args[] = $request['end_date'];
            }
        }
        
        // Add order by
        $query .= " ORDER BY date DESC, id DESC";
        
        // Prepare and execute the query
        $prepared_query = $args ? $wpdb->prepare($query, $args) : $query;
        $visitations = $wpdb->get_results($prepared_query);
        
        // Convert data types for better testing
        foreach ($visitations as &$visitation) {
            $visitation->id = (int) $visitation->id;
            $visitation->patient_id = (int) $visitation->patient_id;
            $visitation->doctor_id = (int) $visitation->doctor_id;
        }
        
        return new \WP_REST_Response($visitations, 200);
    }
    
    /**
     * Get a single visitation
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getVisitation($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $id = $request['id'];
        $visitation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$visitation) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Visitation not found'
            ], 404);
        }
        
        // Convert data types for better testing
        $visitation->id = (int) $visitation->id;
        $visitation->patient_id = (int) $visitation->patient_id;
        $visitation->doctor_id = (int) $visitation->doctor_id;
        
        return new \WP_REST_Response($visitation, 200);
    }
    
    /**
     * Create a new visitation
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function createVisitation($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $data = $request->get_params();
        
        // Basic validation
        if (empty($data['patient_id']) || empty($data['doctor_id']) || empty($data['date'])) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Missing required fields: patient_id, doctor_id, and date are required'
            ], 400);
        }
        
        // Set created_at timestamp
        if (!isset($data['created_at'])) {
            $data['created_at'] = current_time('mysql');
        }
        
        // Insert into database
        $result = $wpdb->insert($table, $data);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to create visitation'
            ], 500);
        }
        
        $visitation_id = $wpdb->insert_id;
        $visitation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $visitation_id));
        
        // Convert data types for better testing
        $visitation->id = (int) $visitation->id;
        $visitation->patient_id = (int) $visitation->patient_id;
        $visitation->doctor_id = (int) $visitation->doctor_id;
        
        return new \WP_REST_Response($visitation, 201);
    }
    
    /**
     * Update a visitation
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function updateVisitation($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $id = $request['id'];
        $data = $request->get_params();
        
        // Check if visitation exists
        $visitation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$visitation) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Visitation not found'
            ], 404);
        }
        
        // Remove ID from data to prevent overwrite
        unset($data['id']);
        
        // Set updated_at timestamp
        if (!isset($data['updated_at'])) {
            $data['updated_at'] = current_time('mysql');
        }
        
        $result = $wpdb->update($table, $data, ['id' => $id]);
        
        if ($result === false) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to update visitation'
            ], 500);
        }
        
        $updated_visitation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        // Convert data types for better testing
        $updated_visitation->id = (int) $updated_visitation->id;
        $updated_visitation->patient_id = (int) $updated_visitation->patient_id;
        $updated_visitation->doctor_id = (int) $updated_visitation->doctor_id;
        
        return new \WP_REST_Response($updated_visitation, 200);
    }
    
    /**
     * Delete a visitation
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function deleteVisitation($request) 
    {
        global $wpdb;
        $table = $wpdb->prefix . 'hm_visitations';
        
        $id = $request['id'];
        
        // Check if visitation exists
        $visitation = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table WHERE id = %d", $id));
        
        if (!$visitation) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Visitation not found'
            ], 404);
        }
        
        $result = $wpdb->delete($table, ['id' => $id]);
        
        if (!$result) {
            return new \WP_REST_Response([
                'success' => false,
                'message' => 'Failed to delete visitation'
            ], 500);
        }
        
        return new \WP_REST_Response([
            'success' => true,
            'message' => 'Visitation deleted successfully'
        ], 200);
    }
}
