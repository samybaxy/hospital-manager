<?php
/**
 * Mock implementation of the Dashboard REST API for testing
 * 
 * This file provides a mock implementation of the Dashboard REST API
 * for unit testing purposes, allowing for controlled responses and
 * status codes.
 */
namespace HospitalManager\Tests\Mocks;

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

        // Ensure patient has both ID and id properties
        if (isset($patient->id)) {
            $patient->ID = $patient->id;
        } elseif (isset($patient->ID)) {
            $patient->id = $patient->ID;
        }

        // Get recent visitations
        $visitations = \HospitalManager\Models\Visitation::forPatient($patient->id);
        
        // Ensure each visitation has both ID and id properties
        if (is_array($visitations)) {
            foreach ($visitations as $visitation) {
                if (isset($visitation->id)) {
                    $visitation->ID = $visitation->id;
                } elseif (isset($visitation->ID)) {
                    $visitation->id = $visitation->ID;
                }
            }
        }

        // Get pending lab tests
        $lab_tests = \HospitalManager\Models\LabInvestigation::getPendingForPatient($patient->id);
        
        // Ensure each lab test has both ID and id properties
        if (is_array($lab_tests)) {
            foreach ($lab_tests as $test) {
                if (isset($test->id)) {
                    $test->ID = $test->id;
                } elseif (isset($test->ID)) {
                    $test->id = $test->ID;
                }
            }
        }

        // Get upcoming appointments
        $appointments = \HospitalManager\Models\Appointment::getUpcomingForPatient($patient->id);
        
        // Ensure each appointment has both ID and id properties
        if (is_array($appointments)) {
            foreach ($appointments as $appointment) {
                if (isset($appointment->id)) {
                    $appointment->ID = $appointment->id;
                } elseif (isset($appointment->ID)) {
                    $appointment->id = $appointment->ID;
                }
            }
        }

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
        
        // Ensure each appointment has both ID and id properties
        if (is_array($todays_appointments)) {
            foreach ($todays_appointments as $appointment) {
                if (isset($appointment->id)) {
                    $appointment->ID = $appointment->id;
                } elseif (isset($appointment->ID)) {
                    $appointment->id = $appointment->ID;
                }
                
                // Handle nested properties if they exist
                if (isset($appointment->meta) && is_object($appointment->meta)) {
                    // Ensure appointment meta also has ID/id properties if needed
                    if (isset($appointment->meta->id)) {
                        $appointment->meta->ID = $appointment->meta->id;
                    } elseif (isset($appointment->meta->ID)) {
                        $appointment->meta->id = $appointment->meta->ID;
                    }
                }
            }
        }
        
        $pending_reports = \HospitalManager\Models\MedicalReport::getPendingForDoctor($user_id);
        
        // Ensure each report has both ID and id properties
        if (is_array($pending_reports)) {
            foreach ($pending_reports as $report) {
                if (isset($report->id)) {
                    $report->ID = $report->id;
                } elseif (isset($report->ID)) {
                    $report->id = $report->ID;
                }
            }
        }

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
        
        // Ensure each test has both ID and id properties
        if (is_array($pending_tests)) {
            foreach ($pending_tests as $test) {
                if (isset($test->id)) {
                    $test->ID = $test->id;
                } elseif (isset($test->ID)) {
                    $test->id = $test->ID;
                }
                
                // Handle nested properties if they exist
                if (isset($test->meta) && is_object($test->meta)) {
                    // Ensure test meta also has ID/id properties if needed
                    if (isset($test->meta->id)) {
                        $test->meta->ID = $test->meta->id;
                    } elseif (isset($test->meta->ID)) {
                        $test->meta->id = $test->meta->ID;
                    }
                }
            }
        }
        
        $completed_tests = \HospitalManager\Models\LabInvestigation::getCompletedCountForTechToday($user_id);

        return new \WP_REST_Response([
            'pending_tests' => $pending_tests ?: [],
            'tests_completed_today' => $completed_tests ?: 0
        ], 200);
    }
}