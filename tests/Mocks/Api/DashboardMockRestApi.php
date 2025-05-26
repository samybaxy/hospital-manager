<?php
/**
 * Mock implementation of the Dashboard REST API for testing
 * 
 * This file provides a mock implementation of the Dashboard REST API
 * for unit testing purposes, allowing for controlled responses and
 * status codes.
 */
namespace HospitalManager\Tests\Mocks\Api;

/**
 * Mock Dashboard REST API class
 */
class DashboardMockRestApi
{
    /**
     * @var string The API namespace
     */
    protected static $namespace = 'hospital-manager/v1';

    public static function register_routes()
    {
        register_rest_route(self::$namespace, '/dashboard', [
            'methods' => 'GET',
            'callback' => [self::class, 'get_dashboard_data'],
            'permission_callback' => [self::class, 'checkLoggedInPermission'],
        ]);
    }

    /**
     * Check if the user has permission to access dashboard data
     *
     * @return bool Whether the user is logged in
     */
    public static function checkLoggedInPermission()
    {
        return is_user_logged_in();
    }

    /**
     * Get dashboard data based on user role
     *
     * @param \WP_REST_Request $request The request object
     * @return \WP_REST_Response Response containing dashboard data
     */
    public static function get_dashboard_data($request)
    {
        $user = wp_get_current_user();
        $role = isset($user->roles) && is_array($user->roles) && !empty($user->roles) ? $user->roles[0] : '';
        
        switch ($role) {
            case 'patient':
                return self::get_patient_dashboard($user->ID);
            case 'doctor':
                return self::get_doctor_dashboard($user->ID);
            case 'lab_tech':
                return self::get_lab_dashboard($user->ID);
            default:
                return new \WP_REST_Response(['error' => 'Invalid role'], 403);
        }
    }

    /**
     * Get dashboard data for a patient
     *
     * @param int $user_id WordPress user ID
     * @return \WP_REST_Response
     */
    private static function get_patient_dashboard($user_id)
    {
        $patients = \HospitalManager\Models\Patient::findWhere(['user_id' => $user_id]);
        $patient = !empty($patients) ? $patients[0] : null;
        
        if (!$patient) {
            return new \WP_REST_Response(['error' => 'Patient not found'], 404);
        }

        // Get recent visitations
        $visitations = \HospitalManager\Models\Visitation::forPatient($patient->ID);

        // Get pending lab tests
        $lab_tests = \HospitalManager\Models\LabInvestigation::getPendingForPatient($patient->ID);

        // Get upcoming appointments
        $appointments = \HospitalManager\Models\Appointment::getUpcomingForPatient($patient->ID);

        return new \WP_REST_Response([
            'patient' => $patient,
            'recent_visitations' => array_slice($visitations ?: [], 0, 5),
            'pending_lab_tests' => $lab_tests ?: [],
            'upcoming_appointments' => $appointments ?: [],
        ]);
    }

    /**
     * Get dashboard data for doctor users
     *
     * @param int $user_id User ID of the doctor
     * @return \WP_REST_Response Response containing doctor dashboard data
     */
    private static function get_doctor_dashboard($user_id)
    {
        $todays_appointments = \HospitalManager\Models\Appointment::getTodaysForDoctor($user_id);        
        $pending_reports = \HospitalManager\Models\MedicalReport::getPendingForDoctor($user_id);

        return new \WP_REST_Response([
            'todays_appointments' => $todays_appointments ?: [],
            'pending_reports' => $pending_reports ?: [],
            'total_patients_today' => is_array($todays_appointments) ? count($todays_appointments) : 0
        ], 200);
    }

    /**
     * Get dashboard data for lab technician users
     *
     * @param int $user_id User ID of the lab technician
     * @return \WP_REST_Response Response containing lab dashboard data
     */
    private static function get_lab_dashboard($user_id)
    {
        $pending_tests = \HospitalManager\Models\LabInvestigation::getPendingForTech($user_id);        
        $completed_tests = \HospitalManager\Models\LabInvestigation::getCompletedCountForTechToday($user_id);

        return new \WP_REST_Response([
            'pending_tests' => $pending_tests ?: [],
            'tests_completed_today' => $completed_tests ?: 0
        ], 200);
    }
}