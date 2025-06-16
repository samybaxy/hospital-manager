<?php

namespace HospitalManager\Controllers\Api;

use HospitalManager\Models\LabInvestigation;
use HospitalManager\Models\Appointment;
use HospitalManager\Models\MedicalReport;
use HospitalManager\Models\Visitation;
use HospitalManager\Models\Patient;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Response;
use HospitalManager\Services\DashboardService;

class DashboardController extends WP_REST_Controller 
{
    public function register_routes() 
    {
        register_rest_route('hospital-manager/v1', '/dashboard', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_data'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        ]);
        
        // Add the missing stats endpoint
        register_rest_route('hospital-manager/v1', '/dashboard/stats', [
            [
                'methods' => WP_REST_Server::READABLE,
                'callback' => [$this, 'get_dashboard_stats'],
                'permission_callback' => [$this, 'check_permissions'],
            ]
        ]);
    }

    public function check_permissions() 
    {
        return is_user_logged_in();
    }

    public function get_dashboard_data($request) 
    {
        $user = wp_get_current_user();
        $role = $user->roles[0];
        
        switch ($role) {
            case 'patient':
                return $this->get_patient_dashboard($user->ID);
            case 'doctor':
                return $this->get_doctor_dashboard($user->ID);
            case 'lab_tech':
                return $this->get_lab_dashboard($user->ID);
            default:
                return new WP_REST_Response(['error' => 'Invalid role'], 403);
        }
    }

    /**
     * Get dashboard data for a patient
     *
     * @param int $user_id WordPress user ID
     * @return WP_REST_Response
     */
    private function get_patient_dashboard($user_id) 
    {
        $patients = Patient::findWhere(['user_id' => $user_id]);
        $patient = !empty($patients) ? $patients[0] : null;
        
        if (!$patient) {
            return new WP_REST_Response(['error' => 'Patient not found'], 404);
        }

        // Get recent visitations
        $visitations = Visitation::forPatient($patient->ID);

        // Get pending lab tests
        $lab_tests = LabInvestigation::getPendingForPatient($patient->ID);

        // Get upcoming appointments
        $appointments = Appointment::getUpcomingForPatient($patient->ID);

        return new WP_REST_Response([
            'patient' => $patient,
            'recent_visitations' => array_slice($visitations, 0, 5),
            'pending_lab_tests' => $lab_tests,
            'upcoming_appointments' => $appointments,
        ]);
    }

    /**
     * Get dashboard data for doctor users
     *
     * @param int $user_id User ID of the doctor
     * @return WP_REST_Response Response containing doctor dashboard data
     */
    private function get_doctor_dashboard($user_id) 
    {
        $todays_appointments = Appointment::getTodaysForDoctor($user_id);
        $pending_reports = MedicalReport::getPendingForDoctor($user_id);

        return new WP_REST_Response([
            'todays_appointments' => $todays_appointments,
            'pending_reports' => $pending_reports,
            'total_patients_today' => count($todays_appointments)
        ], 200);
    }

    /**
     * Get dashboard data for lab technician users
     *
     * @param int $user_id User ID of the lab technician
     * @return WP_REST_Response Response containing lab dashboard data
     */
    private function get_lab_dashboard($user_id) 
    {
        $pending_tests = LabInvestigation::getPendingForTech($user_id);
        $completed_tests = LabInvestigation::getCompletedCountForTechToday($user_id);

        return new WP_REST_Response([
            'pending_tests' => $pending_tests,
            'tests_completed_today' => $completed_tests
        ], 200);
    }

    /**
     * Get dashboard statistics for the admin dashboard
     *
     * @param WP_REST_Request $request API request object
     * @return WP_REST_Response Response containing dashboard stats
     */
    public function get_dashboard_stats($request) {
        try {
            $stats = DashboardService::getDashboardStats();
            return new WP_REST_Response($stats, 200);
            
        } catch (\Exception $e) {
            // Log the error
            error_log('Dashboard stats error: ' . $e->getMessage());
            
            // Return default stats with error message
            $default_stats = DashboardService::getDefaultStats();
            $default_stats['error'] = 'Failed to fetch dashboard statistics';
            
            return new WP_REST_Response($default_stats, 500);
        }
    }
}