<?php
/**
 * Mock implementation of Stats REST API for testing
 * 
 * This file provides test-specific implementations for statistics endpoints
 */

namespace HospitalManager\Tests\Mocks;

/**
 * Mock Stats REST API class for tests
 */
class StatsControllerMockRestApi 
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    /**
     * Register stats REST API routes for testing
     */
    public static function register_routes() 
    {
        // Hospital statistics route
        register_rest_route(self::$namespace, '/stats', [
            'methods' => 'GET',
            'callback' => [self::class, 'getStats'],
            'permission_callback' => [self::class, 'checkAdminPermission'],
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
     * Get hospital-wide statistics
     * 
     * @param \WP_REST_Request $request
     * @return \WP_REST_Response
     */
    public static function getStats($request) 
    {
        // Get test data from createTestData method in the test class
        // Return mock statistics data
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
        
        return rest_ensure_response($stats);
    }
}
