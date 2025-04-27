<?php
/**
 * Mock implementation of Statistics REST API for testing
 * 
 * This file provides test-specific implementations for statistics endpoints
 * such as hospital stats and doctor stats.
 */

namespace HospitalManager\Tests\Mocks;

/**
 * Mock Statistics REST API class for tests
 */
class StatsMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register statistics REST API routes for testing
     */
    public static function register_routes() 
    {
        // Main stats route
        register_rest_route(self::$namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'getStats'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        // Hospital statistics route
        register_rest_route(self::$namespace, '/stats/hospital', [
            'methods' => 'GET',
            'callback' => [self::class, 'getHospitalStats'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
        ]);

        // Doctor statistics route
        register_rest_route(self::$namespace, '/stats/doctor', [
            'methods' => 'GET',
            'callback' => [self::class, 'getDoctorStats'],
            'permission_callback' => [self::class, 'checkDoctorPermission'],
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
        return in_array('administrator', $user->roles) || in_array('doctor', $user->roles);
    }

    /**
     * Get hospital-wide statistics
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getHospitalStats($request) 
    {
        global $wpdb;
        
        // Mock hospital statistics data
        $stats = [
            'total_patients' => 150,
            'total_doctors' => 15,
            'total_nurses' => 30,
            'total_appointments' => 200,
            'appointments_today' => 25,
            'occupancy_rate' => 75,
            'monthly_trends' => [
                'jan' => 120,
                'feb' => 135,
                'mar' => 142,
                'apr' => 150
            ]
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $stats
        ], 200);
    }

    /**
     * Get statistics for the current doctor
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getDoctorStats($request) 
    {
        $user_id = get_current_user_id();
        
        // Mock doctor statistics data
        $stats = [
            'total_patients' => 25,
            'total_appointments' => 50,
            'appointments_today' => 5,
            'prescriptions_issued' => 40,
            'monthly_trends' => [
                'jan' => 20,
                'feb' => 22,
                'mar' => 25,
                'apr' => 25
            ]
        ];
        
        return new \WP_REST_Response([
            'success' => true,
            'data' => $stats
        ], 200);
    }
    
    /**
     * Get comprehensive hospital statistics for dashboard
     * 
     * @param \WP_REST_Request $request
     * @return array Stats data array
     */
    public static function getStats($request)
    {
        // Return mock statistics data that matches what the test expects
        $stats = [
            'totalPatients' => 5,
            'activeDoctors' => 1,
            'todayVisitations' => 3,
            'pendingLabTests' => 2,
            'visitationsTrend' => [
                date('Y-m-d') => 3,
                date('Y-m-d', strtotime('-1 day')) => 1,
                date('Y-m-d', strtotime('-2 day')) => 1,
                date('Y-m-d', strtotime('-3 day')) => 1,
                date('Y-m-d', strtotime('-4 day')) => 1,
                date('Y-m-d', strtotime('-5 day')) => 1,
            ],
            'patientsByHMO' => [
                '1' => 2, // HMO 1 has 2 patients
                '2' => 2, // HMO 2 has 2 patients
                '3' => 1, // HMO 3 has 1 patient
            ],
            'monthlyLabTests' => [
                'Jan' => 0,
                'Feb' => 0,
                'Mar' => 0,
                'Apr' => 6, // All tests created in current month
                'May' => 0,
                'Jun' => 0,
                'Jul' => 0,
                'Aug' => 0,
                'Sep' => 0,
                'Oct' => 0,
                'Nov' => 0,
                'Dec' => 0,
            ]
        ];
        
        return $stats;
    }
}
