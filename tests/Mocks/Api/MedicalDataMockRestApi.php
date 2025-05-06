<?php
/**
 * Mock implementation of Medical Data REST API for testing
 * 
 * This file provides test-specific implementations for medical data endpoints
 * such as lab results and prescriptions.
 */

namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Medical Data REST API class for tests
 */
class MedicalDataMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register medical data REST API routes for testing
     */
    public static function register_routes() 
    {
        // Lab results routes
        register_rest_route(self::$namespace, '/patients/(?P<id>\d+)/lab-results', [
            'methods' => 'GET',
            'callback' => [self::class, 'getLabResults'],
            'permission_callback' => [self::class, 'checkLabResultsPermission'],
        ]);

        // Prescriptions routes
        register_rest_route(self::$namespace, '/patients/(?P<id>\d+)/prescriptions', [
            'methods' => 'GET',
            'callback' => [self::class, 'getPrescriptions'],
            'permission_callback' => [self::class, 'checkPrescriptionsPermission'],
        ]);
    }

    /**
     * Check if user has permission to access lab results
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkLabResultsPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $roles = $user->roles;
        
        // Admin, doctor, and lab tech can access lab results
        return in_array('administrator', $roles) || 
               in_array('doctor', $roles) || 
               in_array('lab_tech', $roles);
    }

    /**
     * Check if user has permission to access prescriptions
     * 
     * @param \WP_REST_Request $request
     * @return bool
     */
    public static function checkPrescriptionsPermission($request) 
    {
        if (!is_user_logged_in()) {
            return false;
        }
        
        $user = wp_get_current_user();
        $roles = $user->roles;
        
        // Admin, doctor, and nurse can access prescriptions
        return in_array('administrator', $roles) || 
               in_array('doctor', $roles) || 
               in_array('nurse', $roles);
    }

    /**
     * Get lab results for a patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getLabResults($request) 
    {
        $patient_id = $request['id'];
        
        // Mock lab results data
        $lab_results = [
            [
                'id' => 1,
                'patient_id' => $patient_id,
                'test_name' => 'Blood Test',
                'test_date' => date('Y-m-d'),
                'results' => 'Normal',
                'notes' => 'All values within acceptable range'
            ],
            [
                'id' => 2,
                'patient_id' => $patient_id,
                'test_name' => 'Urine Analysis',
                'test_date' => date('Y-m-d', strtotime('-1 week')),
                'results' => 'Abnormal',
                'notes' => 'Some elevated levels, further testing required'
            ]
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $lab_results
        ], 200);
    }

    /**
     * Get prescriptions for a patient
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getPrescriptions($request) 
    {
        $patient_id = $request['id'];
        
        // Mock prescriptions data
        $prescriptions = [
            [
                'id' => 1,
                'patient_id' => $patient_id,
                'medication' => 'Amoxicillin',
                'dosage' => '500mg',
                'frequency' => '3 times daily',
                'duration' => '7 days',
                'date_prescribed' => date('Y-m-d'),
                'prescribed_by' => 'Dr. Smith'
            ],
            [
                'id' => 2,
                'patient_id' => $patient_id,
                'medication' => 'Ibuprofen',
                'dosage' => '400mg',
                'frequency' => 'As needed',
                'duration' => '5 days',
                'date_prescribed' => date('Y-m-d', strtotime('-2 days')),
                'prescribed_by' => 'Dr. Smith'
            ]
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $prescriptions
        ], 200);
    }
}
